<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL can leave this newly created table behind when its index creation fails.
        // Complete that interrupted operation safely when rerunning the migration.
        if (Schema::hasTable('newsletter_subscribers')) {
            if (! Schema::hasIndex('newsletter_subscribers', 'newsletter_subscribers_email_unique')) {
                Schema::table('newsletter_subscribers', function (Blueprint $table) {
                    $table->string('email', 190)->change();
                    $table->unique('email');
                });
            }

            return;
        }

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->unique();
            $table->string('status', 20)->default('subscribed')->index();
            $table->timestamp('subscribed_at');
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
