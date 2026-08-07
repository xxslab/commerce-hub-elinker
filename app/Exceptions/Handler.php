<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (Throwable $e, Request $request) {
            if ($e instanceof \Illuminate\Auth\AuthenticationException ||
                $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ||
                $e instanceof \Illuminate\Validation\ValidationException) {
                return null;
            }

            $eventId = (string) Str::uuid();
            Log::error('Unhandled Commerce Hub exception', [
                'event_id' => $eventId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'url' => $request->fullUrl(),
                'user_id' => optional($request->user())->id,
            ]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Wystąpił błąd aplikacji.', 'event_id' => $eventId], 500);
            }

            return response()->view('errors.generic', ['eventId' => $eventId], 500);
        });
    }
}
