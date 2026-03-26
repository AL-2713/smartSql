<?php
ini_set('display_errors', '1');

require "smartSql.php";

/*
    Example smartSql objects
*/

// SELECT query
$selectQuery = new smartSql("select", "levels");
$selectQuery->initSelectVars(["Level_ID", "game", "stars"]);
$selectQuery->addWhere("Level_ID", 231);

$finalSelectQuery = $selectQuery->build();


// UPDATE query
$updateQuery = new smartSql("update", "scores");
$updateQuery->initSet("score", 9021);
$updateQuery->initSet("time", time());
$updateQuery->addWhere("Level_ID", 231);

$finalUpdateQuery = $updateQuery->build();


// Another SELECT query
// Not calling the initSelectVars function, to use the default "*" value
$selectQueryTwo = new smartSql("SELECT", "levels");
$selectQueryTwo->initLimit(20, 40);
$selectQueryTwo->addWhere("Title", "cool", "LIKE", false);

$finalSelectQueryTwo = $selectQueryTwo->build();


// Yet another SELECT query
$selectQueryThr = new smartSql("SELECT", "levels");
$selectQueryThr->addWhere("game", "gooberTime");
$selectQueryThr->initOrder("Level_ID", "ASC");

$finalSelectQueryThr = $selectQueryThr->build();


?>
<html>
<div>Select query: <?= $finalSelectQuery ?></div>
<div>Update query: <?= $finalUpdateQuery ?></div>
<div>Select query: <?= $finalSelectQueryTwo ?></div>
<div>Select query: <?= $finalSelectQueryThr ?></div>
</html>