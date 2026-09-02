<?php

define(
    'BASE_URL',
    getenv('APP_URL') ?: '/public/'
);


define(
    'BASE_PATH',
    dirname(__DIR__, 2)
);


define(
    'APP_NAME',
    'MSU Meeting System'
);


define(
    'GOOGLE_CLIENT_ID',
    getenv('GOOGLE_CLIENT_ID') ?: ''
);