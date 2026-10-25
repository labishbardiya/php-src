--TEST--
GH-24605: unserialize() guards the native stack even when max_depth is disabled
--SKIPIF--
<?php
if (ini_get('zend.max_allowed_stack_size') === false) {
    die('skip Native stack limit support unavailable');
}
if (getenv('SKIP_ASAN')) {
    die('skip ASAN requires a different stack limit setting');
}
?>
--INI--
zend.max_allowed_stack_size=256K
--FILE--
<?php
$depth = 3000;
$input = str_repeat('a:1:{i:0;', $depth) . 'i:0;' . str_repeat('}', $depth);

try {
    unserialize($input, ['max_depth' => 0]);
    echo "NOT GUARDED\n";
} catch (Error $e) {
    echo str_contains($e->getMessage(), 'Maximum call stack size')
        ? "guarded\n" : "unexpected error\n";
}
?>
--EXPECT--
guarded
