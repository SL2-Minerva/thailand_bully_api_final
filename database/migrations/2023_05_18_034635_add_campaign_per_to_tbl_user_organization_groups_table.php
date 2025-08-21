<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCampaignPerToTblUserOrganizationGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_organization_groups', function (Blueprint $table) {
            $table->integer('campaign_per_organize')->nullable();
            $table->integer('campaign_per_user')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_organization_groups', function (Blueprint $table) {
            $table->dropColumn('campaign_per_organize');
            $table->dropColumn('campaign_per_user');
        });
    }
}
