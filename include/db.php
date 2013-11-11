<?php

function db_escape_string($s, $strip_tags = true) {
    return \SmallSmallRSS\Database::escape_string($s, $strip_tags);
}

function db_query($query, $die_on_error = true) {
    return \SmallSmallRSS\Database::query($query, $die_on_error);
}

function db_fetch_assoc($result) {
    return \SmallSmallRSS\Database::fetch_assoc($result);
}


function db_num_rows($result) {
    return \SmallSmallRSS\Database::num_rows($result);
}

function db_fetch_result($result, $row, $param) {
    return \SmallSmallRSS\Database::fetch_result($result, $row, $param);
}

function db_affected_rows($result) {
    return \SmallSmallRSS\Database::affected_rows($result);
}

function db_last_error() {
    return \SmallSmallRSS\Database::last_error();
}

function db_quote($str) {
    return \SmallSmallRSS\Database::quote($str);
}
