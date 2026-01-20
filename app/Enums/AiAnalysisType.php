<?php

namespace App\Enums;

enum AiAnalysisType: string
{
    case Sentiment = 'sentiment';
    case Summary = 'summary';
    case Issue = 'issue';
    case Reply = 'reply';
    case Priority = 'priority';
}
