<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('itmosoft-email-marketing.table_prefix', 'email_marketing_');
        $audiences = $prefix.config('itmosoft-email-marketing.tables.audiences', 'audiences');
        $subscribers = $prefix.config('itmosoft-email-marketing.tables.subscribers', 'subscribers');
        $templates = $prefix.config('itmosoft-email-marketing.tables.templates', 'templates');
        $campaigns = $prefix.config('itmosoft-email-marketing.tables.campaigns', 'campaigns');
        $recipients = $prefix.config('itmosoft-email-marketing.tables.recipients', 'recipients');

        Schema::create($audiences, function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create($subscribers, function (Blueprint $table) use ($audiences): void {
            $table->id();
            $table->foreignId('audience_id')->constrained($audiences)->cascadeOnDelete();
            $table->string('email', 255);
            $table->string('status', 20)->default('pending');
            $table->json('attributes')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->string('consent_source')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
            $table->unique(['audience_id', 'email']);
            $table->index(['audience_id', 'status']);
        });

        Schema::create($templates, function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->longText('html')->nullable();
            $table->longText('text')->nullable();
            $table->timestamps();
        });

        Schema::create($campaigns, function (Blueprint $table) use ($audiences, $templates): void {
            $table->id();
            $table->foreignId('audience_id')->constrained($audiences)->restrictOnDelete();
            $table->foreignId('template_id')->constrained($templates)->restrictOnDelete();
            $table->string('name');
            $table->string('status', 20)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create($recipients, function (Blueprint $table) use ($campaigns, $subscribers): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained($campaigns)->cascadeOnDelete();
            $table->foreignId('subscriber_id')->constrained($subscribers)->restrictOnDelete();
            $table->string('email', 255);
            $table->string('status', 20)->default('pending');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'subscriber_id']);
            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        $prefix = config('itmosoft-email-marketing.table_prefix', 'email_marketing_');

        foreach (['recipients', 'campaigns', 'templates', 'subscribers', 'audiences'] as $key) {
            Schema::dropIfExists($prefix.config("itmosoft-email-marketing.tables.{$key}", $key));
        }
    }
};
