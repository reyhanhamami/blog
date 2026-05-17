<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name', 100)->nullable();
            }

            if (! Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name', 100)->nullable();
            }

            if (! Schema::hasColumn('users', 'job_title')) {
                $table->string('job_title', 150)->nullable();
            }

            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 30)->nullable();
            }

            if (! Schema::hasColumn('users', 'bio')) {
                $table->string('bio', 255)->nullable();
            }

            if (! Schema::hasColumn('users', 'facebook_url')) {
                $table->string('facebook_url')->nullable();
            }

            if (! Schema::hasColumn('users', 'x_url')) {
                $table->string('x_url')->nullable();
            }

            if (! Schema::hasColumn('users', 'linkedin_url')) {
                $table->string('linkedin_url')->nullable();
            }

            if (! Schema::hasColumn('users', 'instagram_url')) {
                $table->string('instagram_url')->nullable();
            }

            if (! Schema::hasColumn('users', 'dribbble_url')) {
                $table->string('dribbble_url')->nullable();
            }

            if (! Schema::hasColumn('users', 'country')) {
                $table->string('country', 120)->nullable();
            }

            if (! Schema::hasColumn('users', 'city_state')) {
                $table->string('city_state', 150)->nullable();
            }

            if (! Schema::hasColumn('users', 'postal_code')) {
                $table->string('postal_code', 20)->nullable();
            }

            if (! Schema::hasColumn('users', 'tax_id')) {
                $table->string('tax_id', 50)->nullable();
            }

            if (! Schema::hasColumn('users', 'profile_photo_path')) {
                $table->string('profile_photo_path', 2048)->nullable();
            }

            if (! Schema::hasColumn('users', 'device_name')) {
                $table->string('device_name', 120)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columns = [
            'first_name',
            'last_name',
            'job_title',
            'phone',
            'bio',
            'facebook_url',
            'x_url',
            'linkedin_url',
            'instagram_url',
            'dribbble_url',
            'country',
            'city_state',
            'postal_code',
            'tax_id',
            'profile_photo_path',
            'device_name',
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
