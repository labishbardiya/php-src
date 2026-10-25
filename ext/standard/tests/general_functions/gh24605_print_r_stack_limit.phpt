--TEST--
GH-24605: print_r() guards native stack on deeply nested arrays and objects
--SKIPIF--
<?php
if (ini_get('zend.max_allowed_stack_size') === false) die('skip No stack limit support');
if (getenv('SKIP_ASAN')) die('skip ASAN requires adjusted stack limits');
?>
--INI--
zend.max_allowed_stack_size=256K
--FILE--
<?php
function checkStackGuard(string $name, mixed $value): void {
    try {
        print_r($value, true);
        echo "$name: no error\n";
    } catch (Error $e) {
        echo "$name: guarded\n";
    }
}

$value = null;
for ($i = 0; $i < 3000; $i++) {
    $value = [$value];
}
checkStackGuard('array', $value);
for ($i = 0; $i < 3000; $i++) {
    $value = $value[0];
}
unset($value);

$value = new stdClass();
for ($i = 0; $i < 3000; $i++) {
    $next = new stdClass();
    $next->child = $value;
    $value = $next;
}
checkStackGuard('object', $value);
for ($i = 0; $i < 3000; $i++) {
    $next = $value->child;
    unset($value->child);
    $value = $next;
}
unset($value);
?>
--EXPECT--
array: guarded
object: guarded
