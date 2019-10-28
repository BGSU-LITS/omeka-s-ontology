<?php
require __DIR__ . '/vendor/autoload.php';

use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Common\Type;
use Commando\Command;

$command = new Command();
$command->setHelp(<<<EOT
Converts a spreadsheet to SQL statements to make CSV files for Omeka-S import.

Example usage:
php database_mapping.php ontology.xlsx table > database_mapping.sql
EOT
);

$command->option()
    ->referToAs('filename')
    ->description('Filename of spreadsheet to process. Required.');

$command->option()
    ->referToAs('table')
    ->description('Table within the speadsheet to process. Required.');

$command->option('m')
    ->alias('mappings')
    ->default('Database Mappings')
    ->description(
        'Sheet which defines database mappings. Default: Database Mappings'
    );

if (empty($command[0]) || empty($command[1])) {
    $command->printHelp();
    exit;
}

switch (pathinfo($command[0], PATHINFO_EXTENSION)) {
    case 'csv':
        $type = Type::CSV;
        break;
    case 'ods':
        $type = Type::ODS;
        break;
    case 'xlsx':
    default:
        $type = Type::XLSX;
        break;
}

$reader = ReaderFactory::create($type);
$reader->open($command[0]);

foreach ($reader->getSheetIterator() as $sheet) {
    if ($sheet->getName()) {
        $sheets[$sheet->getName()] = $sheet;
    }
}

if (empty($sheets)) {
    $sheets[$command['m']] = $sheet;
}

$header = [];
$select = [];
$column = [];
$join = [];
$order = [];

foreach ($sheets[$command['m']]->getRowIterator() as $row) {
    if (empty($header)) {
        $header = $row;
        continue;
    }

    $data = array_combine($header, array_pad($row, sizeof($header), ''));

    if ($data['Table'] !== $command[1]) {
        continue;
    }

    if (!empty($data['Column'])) {
        $select[] = '`' . $data['Column'] . '`';
        $column[] = $data['Field'] . ' as `' . $data['Column'] . '`';
    } elseif (!empty($data['Join']) && !empty($data['On'])) {
        $join[] = ' LEFT JOIN ' . $data['Join'] . ' ON ' .
            $data['Field'] . ' = ' . $data['On'];
    } else {
        $column[] = $data['Field'];
    }

    if (!empty($data['Order'])) {
        $order[] = $data['Field'] . ' ' . $data['Order'];
    }
}

echo 'SELECT ' . implode($select, ', ') . ' FROM (';
echo 'SELECT DISTINCT ' . implode($column, ', ') . ' FROM ';
echo $command[1] . implode($join, '');

if (!empty($order)) {
    echo ' ORDER BY ' . implode($order, ', ');
}

echo ') as main;' . PHP_EOL;
