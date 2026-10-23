<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('tr:server-lifecycle')->everyMinute()->withoutOverlapping();
