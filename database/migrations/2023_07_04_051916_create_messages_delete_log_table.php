<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMessagesDeleteLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('messages_delete_log', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 200)->nullable();
            $table->string('reference_message_id', 200)->nullable();
            $table->unsignedBigInteger('keyword_id');
            $table->timestamp('message_datetime')->nullable();
            $table->string('author', 200)->nullable();
            $table->unsignedBigInteger('source_id');
            $table->text('full_message');
            $table->string('link_message', 250)->nullable();
            $table->string('message_type', 100)->nullable();
            $table->string('device', 100)->nullable();
            $table->integer('number_of_shares')->nullable();
            $table->integer('number_of_comments')->nullable();
            $table->integer('number_of_reactions')->nullable();
            $table->integer('number_of_views')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('id', 'idx_tbl_id');
            $table->index('source_id', 'idx_source');
            $table->index('message_datetime', 'idx_date_m');
            $table->index('message_id', 'idx_message_id');
            $table->index('keyword_id', 'idx_keyword_id');
            $table->index('author', 'idx_author');
            $table->index('device', 'idx_device');
            $table->index('link_message', 'idx_link_message');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('messages_delete_log');
    }
}
