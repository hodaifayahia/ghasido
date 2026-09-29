<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Requests\VoiceAgent\VoiceReplyRequest;
use App\Models\RoleplayAttempt;
use App\Services\VoiceAgent\VoiceReplyService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The guest's answer to one finished sentence in a fast-engine call
 * (spec 0009), shared by the learner's call and the admin's test call.
 * Each controller checks who owns the attempt before calling this.
 */
trait AnswersVoiceReplies
{
    protected function answerVoiceReply(RoleplayAttempt $attempt, VoiceReplyRequest $request, VoiceReplyService $replies): JsonResponse
    {
        $scenario = $attempt->scenario;

        if ($scenario === null || ! $attempt->status->acceptsTurns()) {
            return response()->json(['message' => __('This call has ended.')], 409);
        }

        try {
            return response()->json($replies->reply($attempt, $scenario, $request->turn(), $request->rev(), $request->text()));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }
    }
}
