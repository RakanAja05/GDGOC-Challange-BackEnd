<?php

namespace App\Providers;

use App\Services\AIAnalysisService;
use App\Services\AI\Handlers\IssueClassificationHandler;
use App\Services\AI\Handlers\PriorityHandler;
use App\Services\AI\Handlers\SentimentHandler;
use App\Services\AI\Handlers\SuggestedReplyHandler;
use App\Services\AI\Handlers\SummaryHandler;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->tag([
            SentimentHandler::class,
            SummaryHandler::class,
            IssueClassificationHandler::class,
            SuggestedReplyHandler::class,
            PriorityHandler::class,
        ], 'ai.handlers');

        $this->app->bind(AIAnalysisService::class, function ($app) {
            return new AIAnalysisService($app->tagged('ai.handlers'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });
    }
}
