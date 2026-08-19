<?php
declare(strict_types=1);

/**
 * erased_split_sql_statements() (app/bootstrap.php) - the backup restore
 * parser. Regression test for the real bug: the old plain
 * explode(";\n", $sql) cut straight through a `;\n` sequence sitting inside
 * a quoted string value (a post body containing "...end.;\nNext..." is
 * entirely plausible), silently truncating/corrupting the following
 * statement. This covers that the quote-aware splitter gets it right.
 */

require_once dirname(__DIR__).'/app/bootstrap.php';

$fail = 0;
$check = static function (bool $condition, string $label) use (&$fail): void {
    if ($condition) {
        echo "PASS: {$label}\n";
    } else {
        fwrite(STDERR, "FAIL: {$label}\n");
        $fail++;
    }
};

try {
    // --- Plain case: two ordinary statements ---
    $sql = "INSERT INTO t (a) VALUES (1);\nINSERT INTO t (a) VALUES (2);\n";
    $statements = erased_split_sql_statements($sql);
    $check(count($statements) === 2, 'two plain statements split into two');
    $check(trim($statements[0]) === "INSERT INTO t (a) VALUES (1);", 'first statement is intact');
    $check(trim($statements[1]) === "INSERT INTO t (a) VALUES (2);", 'second statement is intact');

    // --- The real bug: a `;\n` sequence embedded inside a quoted string value ---
    $sql = "INSERT INTO content (body) VALUES ('End of sentence;\nStart of next paragraph.');\nINSERT INTO t (a) VALUES (2);\n";
    $statements = erased_split_sql_statements($sql);
    $check(count($statements) === 2, 'a `;\\n` inside a quoted string does NOT split the statement (the actual bug being fixed)');
    $check(str_contains($statements[0], "Start of next paragraph") && str_contains($statements[0], 'VALUES (2)') === false, 'the embedded ;\\n text stays inside the first statement, not bleeding into the second');
    $check(count($statements) === 2 && trim($statements[1]) === "INSERT INTO t (a) VALUES (2);", 'the second, genuinely separate statement is still parsed correctly');

    // --- A backtick-quoted identifier containing something that looks like a statement end is not split either ---
    $sql = "INSERT INTO `weird;\ntable` (a) VALUES (1);\n";
    $statements = erased_split_sql_statements($sql);
    $check(count($statements) === 1, 'a `;\\n` inside a backtick-quoted identifier does not split the statement');

    // --- An escaped quote inside a string (PDO::quote()'s own backslash-escaping) doesn't end the string early ---
    $sql = "INSERT INTO t (a) VALUES ('it\\'s a test;\ncontinued');\nINSERT INTO t (a) VALUES (2);\n";
    $statements = erased_split_sql_statements($sql);
    $check(count($statements) === 2, "a backslash-escaped quote inside a string doesn't prematurely end the string and cause a bad split");

    // --- Real round-trip: a value containing a literal `;\n`, quoted via PDO::quote() exactly as backup_database() does it, survives the split unchanged ---
    $pdo = new PDO('sqlite::memory:');
    $tricky = "First line;\nSecond line";
    $quoted = $pdo->quote($tricky);
    $sql = "INSERT INTO t (body) VALUES ({$quoted});\nINSERT INTO t (body) VALUES ('plain');\n";
    $statements = array_values(array_filter(array_map('trim', erased_split_sql_statements($sql)), static fn($s) => $s !== '' && !str_starts_with($s, '--')));
    $check(count($statements) === 2, 'a PDO::quote()-escaped value with an embedded ;\\n round-trips as exactly two statements, not glued or split mid-value');
    $check($statements[0] === "INSERT INTO t (body) VALUES ({$quoted});", 'the quoted statement text is byte-for-byte unchanged by the split');

    if ($fail === 0) {
        fwrite(STDOUT, "SQL statement splitter test passed.\n");
        fwrite(STDOUT, "Validated the ;\\n-inside-a-quoted-string bug is fixed, backtick identifiers and escaped quotes are respected, and normal statements still split correctly.\n");
    } else {
        exit(1);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
