<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('reference')->unique();
            $table->string('idempotency_key')->unique();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->string('payer_uid');
            $table->string('payee_uid')->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 8)->default('TZS');
            $table->unsignedBigInteger('platform_fee')->default(0);
            $table->unsignedBigInteger('payee_amount')->default(0);
            $table->string('provider')->default('malipo');
            $table->string('provider_reference')->nullable()->index();
            $table->string('provider_external_reference')->nullable();
            $table->string('provider_link')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('access_granted_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['payer_uid', 'status']);
            $table->index(['payee_uid', 'status']);
            $table->index(['type', 'status']);
        });

        Schema::create('wallet_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('owner_uid');
            $table->string('owner_type');
            $table->string('owner_name')->nullable();
            $table->string('currency', 8)->default('TZS');
            $table->bigInteger('balance')->default(0);
            $table->bigInteger('available_balance')->default(0);
            $table->bigInteger('total_earned')->default(0);
            $table->bigInteger('total_withdrawn')->default(0);
            $table->timestamps();

            $table->unique(['owner_uid', 'owner_type', 'currency']);
        });

        Schema::create('wallet_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_account_id')->constrained()->cascadeOnDelete();
            $table->string('entry_type');
            $table->bigInteger('amount');
            $table->string('currency', 8)->default('TZS');
            $table->string('source_type');
            $table->string('source_id');
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'entry_type']);
            $table->index(['wallet_account_id', 'created_at']);
        });

        Schema::create('withdrawal_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('reference')->unique();
            $table->string('type');
            $table->string('status')->default('processing');
            $table->string('owner_uid');
            $table->string('owner_name')->nullable();
            $table->unsignedBigInteger('requested_amount');
            $table->unsignedBigInteger('platform_fee')->default(0);
            $table->decimal('platform_fee_percent', 5, 2)->default(0);
            $table->unsignedBigInteger('payout_amount');
            $table->string('currency', 8)->default('TZS');
            $table->string('payment_method')->default('Mobile Money');
            $table->string('recipient_phone');
            $table->string('provider')->default('malipo');
            $table->string('provider_reference')->nullable()->index();
            $table->string('provider_external_reference')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->index(['owner_uid', 'type', 'status']);
        });

        Schema::create('provider_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->default('malipo');
            $table->string('direction');
            $table->string('reference')->index();
            $table->string('provider_reference')->nullable()->index();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            $table->string('currency', 8)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('provider_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->default('malipo');
            $table->string('event_key')->unique();
            $table->string('event_type')->nullable();
            $table->string('reference')->nullable()->index();
            $table->string('status')->default('received');
            $table->json('payload');
            $table->text('processing_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_events');
        Schema::dropIfExists('provider_transactions');
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('wallet_ledger_entries');
        Schema::dropIfExists('wallet_accounts');
        Schema::dropIfExists('payment_orders');
    }
};
