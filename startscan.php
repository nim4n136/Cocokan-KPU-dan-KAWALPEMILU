#!/usr/bin/php
<?php
include 'ScanKPU.php';
echo "\n";
echo 'INFO!!'.PHP_EOL;
echo 'Program ini hanya mencari data yang tidak sama antara website pemilu2019.kpu.go.id dan kawalpemilu.org'.PHP_EOL;
echo 'Jika ada data yang tidak sama tersimpan otomatis'.PHP_EOL;
echo "\n";
sleep(2);

$instance = new ScanKPU();

echo '========== Daftar Provinsi ==========='.PHP_EOL;
$instance->showProvinci();

echo '===================================='.PHP_EOL;
echo 'Masukan ID Provinsi: ';
$input = readStdin();

echo 'Loading .... '.PHP_EOL;

$instance->startByProvinsiID($input);

function readStdin()
{
    $openFrom = fopen('php://stdin', 'r');
    $input = fgets($openFrom, 128);
    $input = rtrim($input);
    fclose($openFrom);

    return $input;
}
