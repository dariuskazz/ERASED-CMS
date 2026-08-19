import assert from 'node:assert/strict';
import test from 'node:test';
import { parseHTML } from 'linkedom';

const { window } = parseHTML('<html><body></body></html>');
globalThis.window = window;
globalThis.document = window.document;

const {
  sanitizeAttributes,
  sanitizeHtmlFragment,
  sanitizeUrl,
} = await import('../resources/editor/sanitizeHtml.js');

test('dangerous executable HTML is removed', () => {
  const clean = sanitizeHtmlFragment(
    '<script>alert(1)</script>'
    + '<img src="/safe.jpg" onerror="alert(2)">'
    + '<a href="javascript:alert(3)">unsafe</a>',
  );

  assert.doesNotMatch(clean, /script|onerror|javascript:/i);
  assert.match(clean, /src="\/safe\.jpg"/);
});

test('only supported embed hosts remain', () => {
  const clean = sanitizeHtmlFragment(
    '<iframe src="https://www.youtube-nocookie.com/embed/abc"></iframe>'
    + '<iframe src="https://attacker.example/phishing"></iframe>',
  );

  assert.match(clean, /youtube-nocookie\.com/);
  assert.match(clean, /sandbox="allow-scripts allow-same-origin allow-presentation"/);
  assert.doesNotMatch(clean, /attacker\.example/);
});

test('attribute and URL filtering preserves safe media metadata', () => {
  assert.equal(sanitizeUrl('javascript:alert(1)', 'IMG', 'src'), '');
  assert.equal(sanitizeUrl('/media/image.jpg', 'IMG', 'src'), '/media/image.jpg');

  assert.deepEqual(
    sanitizeAttributes('IMG', {
      src: '/media/image.jpg',
      alt: 'Example',
      onload: 'alert(1)',
      style: 'width: 320px; position: fixed; background-image: url(javascript:alert(1))',
      'data-align': 'center',
    }),
    {
      src: '/media/image.jpg',
      alt: 'Example',
      style: 'width: 320px',
      'data-align': 'center',
    },
  );
});
