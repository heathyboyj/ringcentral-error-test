<?php

Route::view('/', 'welcome');

Route::get('index', 'RingCentralController@index')->name('index');
Route::get('integrations/ringcentral/oauth2', 'RingCentralController@callback')->name('callback');
