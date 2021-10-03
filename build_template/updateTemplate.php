<?php

$lines = file_get_contents(__DIR__ . "/metric_sample.txt");
$lines = explode("\n", $lines);

$currentType = "";

$typeKeys = [];

// Parse the metric sample for unique keys foreach TYPE
foreach ($lines as $line) {
    if ($line == "") {
        continue;
    }
    if (strpos($line, '# HELP') === 0) {
        continue;
    }
    if (strpos($line, '# TYPE') === 0) {
        $currentType = str_replace("# TYPE", "", $line);
        $currentType = str_replace("counter", "", $currentType);
        $currentType = str_replace("gauge", "", $currentType);
        $currentType = trim($currentType);
        $currentType = rtrim($currentType);
        $typeKeys[$currentType] = [];
        continue;
    }
    // Get the paramaters ready to be treated like a HTTP query string
    $x = str_replace($currentType, "", $line);
    $x = explode(" ", $x)[0];
    $x = str_replace("{", "", $x);
    $x = str_replace("}", "", $x);
    $x = str_replace(",", "&", $x);
    $x = str_replace("\"", "", $x);
    // Parse paramaters as HTTP query string
    $o = [];
    parse_str($x, $o);
    // Store all the key combinations seen for this type
    $keys = array_keys($o);
    $strVersion = implode($keys);
    $typeKeys[$currentType][$strVersion] = $keys;
}

$zabbixItems = [];

// Replace last occurnce of string https://stackoverflow.com/a/3835653/4008082
function str_lreplace($search, $replace, $subject)
{
    $pos = strrpos($subject, $search);

    if ($pos !== false) {
        $subject = substr_replace($subject, $replace, $pos, strlen($search));
    }

    return $subject;
}
// The LLD macros we need to export
$allSeenKeys = [];

$zabbixItemUniqueIds = json_decode(file_get_contents(__DIR__ . "/uniqueIds.json"), true);

foreach ($typeKeys as $type => $t) {
    $allTypes = [];
    foreach ($t as $types) {
        $allTypes = array_merge($types, $allTypes);
    }
    $allTypes = array_unique($allTypes);

    $shortKeys = array_keys($t);

    usort($shortKeys, function ($a, $b) {
        return strlen($a) > strlen($b);
    });

    foreach ($shortKeys as $key) {
        // foreach ( as $d => $keys) {
        $uniqueKeys = $t[$key];

        $notSeen = array_diff($allTypes, $uniqueKeys);

        $allSeenKeys = array_merge($allSeenKeys, $uniqueKeys);

        usort($uniqueKeys, function ($a, $b) {
            // Get some consistent ordering going on
            $typeWeights = [
                "project"=>1,
                "name"=>2,
                "type"=>3,
                "device"=>4,
                "cpu"=>5,
                "mode"=>6
            ];

            $aWeight = isset($typeWeights[$a]) ? $typeWeights[$a] : 999999999;
            $bWeight = isset($typeWeights[$b]) ? $typeWeights[$b] : 999999999;

            return $aWeight > $bWeight;
        });

        $zabbixItemName = "$type ";
        $zabbixItemKey = $type . '[';
        $zabbixPreProcessPattern = $type . '{';

        foreach ($uniqueKeys as $key) {
            $zabbixPreProcessPattern .= $key . '="';
            $key = strtoupper($key);
            $zabbixPreProcessPattern .= '{#' . $key . '}",';
            $zabbixItemName .= "{#$key} ";
            $zabbixItemKey .= "{#$key} ";
        }

        foreach ($notSeen as $key) {
            $zabbixPreProcessPattern .= ',' . $key . '!~".*"';
            strtoupper($key);
            $zabbixItemName .= "!{#$key} ";
            $zabbixItemKey .= "!{#$key} ";
        }

        $zabbixItemKey = rtrim($zabbixItemKey);
        $zabbixPreProcessPattern = str_lreplace(",", "", $zabbixPreProcessPattern);
        $zabbixPreProcessPattern = rtrim($zabbixPreProcessPattern);

        $zabbixItemKey .= "]";
        $zabbixPreProcessPattern .= "}";

        $uniqueId = isset($zabbixItemUniqueIds[$zabbixItemKey]) ? $zabbixItemUniqueIds[$zabbixItemKey] : uniqid();

        $zabbixItemUniqueIds[$zabbixItemKey] = $uniqueId;

        $zabbixItems[] = [
            "name"=>$zabbixItemName,
            "type"=>"DEPENDENT",
            "key" => $zabbixItemKey,
            "delay"=>'0',
            "uuid"=>$uniqueId,
            "preprocessing"=>[
                [
                    "type"=>"PROMETHEUS_PATTERN",
                    "parameters"=>[
                        $zabbixPreProcessPattern,
                        ""
                    ]
                ]
            ],
            "master_item"=>[
              "key"=>"metrics"
            ]
        ];
        // }
    }
}

$uniqueKeys = array_unique($allSeenKeys);

$lldMacroPaths = [
    [
        "lld_macro"=>"{#METRIC}",
        "path"=>'$[\'name\']',
    ]
];

foreach ($uniqueKeys as $key) {
    $lldMacroPaths[] = [
        "lld_macro"=>'{#' . strtoupper($key) . '}',
        "path"=>'$.labels[\'' . strtolower($key) . '\']',
    ];
}

$template = json_decode(file_get_contents(__DIR__ . "/zbx_base_template.json"), true);
$template["zabbix_export"]["templates"][0]["discovery_rules"][0]["item_prototypes"] = $zabbixItems;
$template["zabbix_export"]["templates"][0]["discovery_rules"][0]["lld_macro_paths"] = $lldMacroPaths;

file_put_contents(__DIR__ . "/uniqueIds.json", json_encode($zabbixItemUniqueIds, JSON_PRETTY_PRINT));
file_put_contents(__DIR__ . "/../lxd-metrics-template.json", json_encode($template, JSON_PRETTY_PRINT));
