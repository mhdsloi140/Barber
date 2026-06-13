<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Log;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
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
     */
    public function register(): void
    {
          $this->renderable(function (\ErrorException $e, $request) {
      if (str_contains($e->getMessage(), 'preg_match')) {
            Log::error('preg_match error in file: ' . $e->getFile());
            Log::error('Line: ' . $e->getLine());
            Log::error('Message: ' . $e->getMessage());

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ في التحقق من البيانات',
                    'code' => 'VALIDATION_ERROR'
                ], 422);
            }
        }
    });
    }
}
