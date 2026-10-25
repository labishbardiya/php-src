--TEST--
GH-24605: print_r() throws instead of overflowing the native call stack
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
memory_limit=-1
--FILE--
<?php
function check_print_r(string $label, mixed $value): void {
    try {
        print_r($value, true);
        echo "$label: NOT GUARDED\n";
    } catch (Error $e) {
        echo $label, ': ', str_contains($e->getMessage(), 'Maximum call stack size')
            ? "guarded\n" : "unexpected error\n";
    }
}

$array = 0;
for ($i = 0; $i < 3000; $i++) {
    $array = [$array];
}
check_print_r('array', $array);

// The output-writing mode must not emit a partial rendering on failure.
ob_start();
try {
    print_r($array);
    ob_end_clean();
    echo "output: NOT GUARDED\n";
} catch (Error $e) {
    $output = ob_get_clean();
    echo str_contains($e->getMessage(), 'Maximum call stack size') && $output === ''
        ? "output: guarded\n" : "output: unexpected error\n";
}
while (is_array($array)) {
    $array = $array[0];
}

class PrintStackNode { public ?PrintStackNode $next = null; }
$first = new PrintStackNode();
$node = $first;
for ($i = 0; $i < 3000; $i++) {
    $node->next = new PrintStackNode();
    $node = $node->next;
}
check_print_r('object', $first);
$node = $first;
while ($node !== null) {
    $next = $node->next;
    $node->next = null;
    $node = $next;
}
?>
--EXPECT--
array: guarded
output: guarded
object: guarded
