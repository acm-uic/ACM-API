<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
			$table->increments('id');
			$table->integer('eid')->comment('Event id from Events Table.');
			//$table->foreign('eid')->references('id')->on('events');
			$table->integer('uin');
			//$table->foreign('uin')->references('uin')->on('users');
			$table->integer('point')->comment('Number of points to add or subtract. Can be negative.');
			$table->text('data')->comment('Additional metadata if needed.')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}
