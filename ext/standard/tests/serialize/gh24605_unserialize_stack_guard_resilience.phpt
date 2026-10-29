--TEST--
GH-24605: unserialize() native stack guard, object and array cleanup, and logical max_depth semantics
--SKIPIF--
<?php
if (ini_get('zend.max_allowed_stack_size') === false) {
    die('skip Native stack limit support unavailable');
}
if (getenv('SKIP_ASAN')) {
    die('skip ASAN requires different stack settings');
}
?>
--INI--
zend.max_allowed_stack_size=256K
memory_limit=128M
--FILE--
<?php
function check_unserialize_guard(string $label, string $input): void {
    try {
        unserialize($input, ['max_depth' => 0]);
        echo "$label: no error\n";
    } catch (Error $e) {
        echo $label, ': ', str_contains($e->getMessage(), 'Maximum call stack size')
            ? "stack error\n"
            : "wrong error\n";
    }
}

$depth = 5000;
$arrayInput = str_repeat('a:1:{i:0;', $depth) . 'i:0;' . str_repeat('}', $depth);
check_unserialize_guard('array', $arrayInput);

$objectInput = str_repeat('O:8:"stdClass":1:{s:5:"child";', $depth)
    . 'N;' . str_repeat('}', $depth);
check_unserialize_guard('object', $objectInput);

$normal = unserialize('a:1:{i:0;i:42;}');
echo ($normal === [42]) ? "normal: ok\n" : "normal: failed\n";

// This uses the logical depth cap rather than the native stack guard.
$nested = str_repeat('a:1:{i:0;', 5) . 'i:0;' . str_repeat('}', 5);
set_error_handler(static fn() => true);
try {
    $limited = unserialize($nested, ['max_depth' => 3]);
} finally {
    restore_error_handler();
}
echo ($limited === false) ? "depth cap: ok\n" : "depth cap: failed\n";
?>
--EXPECT--
array: stack error
object: stack error
normal: ok
depth cap: ok
