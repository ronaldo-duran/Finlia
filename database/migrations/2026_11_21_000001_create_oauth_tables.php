<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_clients', function (Blueprint $table): void {
            $table->id();
            $table->string('client_id', 80)->unique();
            $table->string('client_name', 120);
            $table->json('redirect_uris');
            $table->json('grant_types');
            $table->json('response_types');
            $table->string('token_endpoint_auth_method', 40);
            $table->string('scope', 255)->nullable();
            $table->string('client_uri', 500)->nullable();
            $table->string('logo_uri', 500)->nullable();
            $table->string('software_id', 80)->nullable();
            $table->string('software_version', 80)->nullable();
            $table->timestamps();
        });

        Schema::create('oauth_authorization_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code_hash', 64)->unique();
            $table->foreignId('oauth_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('redirect_uri', 500);
            $table->string('code_challenge', 128);
            $table->string('code_challenge_method', 10);
            $table->string('scope', 255);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['oauth_client_id', 'user_id']);
        });

        Schema::create('oauth_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('oauth_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 255);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
            $table->index(['household_id', 'revoked_at']);
        });

        Schema::create('oauth_refresh_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('oauth_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('oauth_access_token_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope', 255);
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_refresh_tokens');
        Schema::dropIfExists('oauth_access_tokens');
        Schema::dropIfExists('oauth_authorization_codes');
        Schema::dropIfExists('oauth_clients');
    }
};
