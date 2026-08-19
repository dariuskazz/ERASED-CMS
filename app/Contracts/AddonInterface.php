<?php
declare(strict_types=1);
namespace Erased\Contracts;
interface AddonInterface {public function register(): void; public function boot(): void;}
