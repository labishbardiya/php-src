--TEST--
GH-24605: print_r() native stack overflow is caught, recursion guards and normal calls survive
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
zend.max_allowed_stack_size=128K
memory_limit=128M
--FILE--
<?php
function check_print_guard(string $label, mixed $value): void {
    try {
        print_r($value, true);
        echo "$label: no error\n";
    } catch (Error $e) {
        echo $label, ': ', str_contains($e->getMessage(), 'Maximum call stack size')
            ? "stack error\n"
            : "wrong error\n";
    }
}

$value = null;
for ($i = 0; $i < 5000; $i++) {
    $value = [$value];
}
check_print_guard('array', $value);
// Release the deeply nested arrays iteratively.
while (is_array($value)) {
    $value = $value[0];
}
unset($value);

$value = new stdClass();
for ($i = 0; $i < 5000; $i++) {
    $next = new stdClass();
    $next->child = $value;
    $value = $next;
}
check_print_guard('object', $value);
// Prevent recursive teardown of thousands of nested objects.
while (isset($value->child)) {
    $next = $value->child;
    unset($value->child);
    $value = $next;
}
unset($value);

$normal = print_r(['answer' => 42], true);
echo str_contains($normal, '[answer] => 42') ? "normal: ok\n" : "normal: failed\n";

$cycle = [];
$cycle['self'] = &$cycle;
$output = print_r($cycle, true);
echo str_contains($output, '*RECURSION*') ? "cycle: ok\n" : "cycle: failed\n";
unset($cycle['self']);
?>
--EXPECT--
array: stack error
object: stack error
normal: ok
cycle: ok
