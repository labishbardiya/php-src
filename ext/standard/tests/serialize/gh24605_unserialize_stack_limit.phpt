--TEST--
GH-24605: unserialize() guards native stack before max_depth
--SKIPIF--
<?php
if (ini_get('zend.max_allowed_stack_size') === false) die('skip No stack limit support');
if (getenv('SKIP_ASAN')) die('skip ASAN requires adjusted stack limits');
?>
--INI--
zend.max_allowed_stack_size=256K
unserialize_max_depth=0
--FILE--
<?php
$depth = 5000;
$input = str_repeat('a:1:{i:0;', $depth) . 'i:0;' . str_repeat('}', $depth);
try {
    unserialize($input, ['max_depth' => 0]);
    echo "unserialize: no error\n";
} catch (Error $e) {
    echo str_contains($e->getMessage(), 'Maximum call stack size')
        ? "unserialize: guarded\n"
        : "unserialize: unexpected error\n";
}
echo unserialize('a:1:{i:0;i:42;}')[0] === 42 ? "recovered\n" : "bad recovery\n";
?>
--EXPECT--
unserialize: guarded
recovered
