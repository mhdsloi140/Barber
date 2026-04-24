<?php
// app/Services/Rating/RatingService.php

namespace App\Services\Rating;

use App\Models\User;
use App\Models\Salon;
use App\Models\Rating;
use App\Models\Appointment;
use App\Services\AuthResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RatingService
{
    /**
     * إضافة تقييم جديد (للصالون والحلاق بشكل منفصل)
     */
    public function addRating(array $data): AuthResult
    {
        try {
            return DB::transaction(function () use ($data) {

                $customer = auth()->user();
                $appointment = Appointment::find($data['appointment_id']);

                $ratingsAdded = [];

             
                if (isset($data['barber_rating']) && $data['barber_rating']) {
                    // التحقق من عدم وجود تقييم مسبق للحلاق لهذا الحجز
                    $existingBarberRating = Rating::where('appointment_id', $data['appointment_id'])
                        ->where('barber_id', $appointment->barber_id)
                        ->first();

                    if (!$existingBarberRating) {
                        $barberRating = Rating::create([
                            'customer_id' => $customer->id,
                            'barber_id' => $appointment->barber_id,
                            'salon_id' => null,
                            'appointment_id' => $data['appointment_id'],
                            'rating' => $data['barber_rating'],
                            'comment' => $data['barber_comment'] ?? null,
                            'is_approved' => true,
                        ]);

                        $ratingsAdded[] = [
                            'type' => 'barber',
                            'rating' => $barberRating->rating,
                            'comment' => $barberRating->comment,
                        ];

                        Log::info('Barber rating added', [
                            'rating_id' => $barberRating->id,
                            'barber_id' => $appointment->barber_id,
                        ]);
                    }
                }


                if (isset($data['salon_rating']) && $data['salon_rating']) {
                    // التحقق من عدم وجود تقييم مسبق للصالون لهذا الحجز
                    $existingSalonRating = Rating::where('appointment_id', $data['appointment_id'])
                        ->where('salon_id', $appointment->salon_id)
                        ->first();

                    if (!$existingSalonRating) {
                        $salonRating = Rating::create([
                            'customer_id' => $customer->id,
                            'barber_id' => null,
                            'salon_id' => $appointment->salon_id,
                            'appointment_id' => $data['appointment_id'],
                            'rating' => $data['salon_rating'],
                            'comment' => $data['salon_comment'] ?? null,
                            'is_approved' => true,
                        ]);

                        $ratingsAdded[] = [
                            'type' => 'salon',
                            'rating' => $salonRating->rating,
                            'comment' => $salonRating->comment,
                        ];

                        Log::info('Salon rating added', [
                            'rating_id' => $salonRating->id,
                            'salon_id' => $appointment->salon_id,
                        ]);
                    }
                }

                if (empty($ratingsAdded)) {
                    return AuthResult::error('تم إضافة التقييمات مسبقاً لهذا الحجز', null, 400);
                }

                return AuthResult::success('تم إضافة التقييمات بنجاح', [
                    'ratings' => $ratingsAdded,
                ], 201);

            });
        } catch (\Exception $e) {
            Log::error('Add rating error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء إضافة التقييم: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * جلب تقييمات الحلاق
     */
    public function getBarberRatings(int $barberId): AuthResult
    {
        try {
            $barber = User::whereHasRole('barber')->find($barberId);

            if (!$barber) {
                return AuthResult::error('الحلاق غير موجود', null, 404);
            }

            $ratings = Rating::where('barber_id', $barberId)
                ->where('is_approved', true)
                ->with('customer')
                ->orderBy('created_at', 'desc')
                ->get();

            $averageRating = $ratings->count() > 0 ? round($ratings->avg('rating'), 1) : 0;
            $totalRatings = $ratings->count();

            $ratingDistribution = [
                5 => $ratings->where('rating', 5)->count(),
                4 => $ratings->where('rating', 4)->count(),
                3 => $ratings->where('rating', 3)->count(),
                2 => $ratings->where('rating', 2)->count(),
                1 => $ratings->where('rating', 1)->count(),
            ];

            $formattedRatings = $ratings->map(function ($rating) {
                return [
                    'id' => $rating->id,
                    'customer_name' => $rating->customer->name,
                    'rating' => $rating->rating,
                    'comment' => $rating->comment,
                    'created_at' => $rating->created_at->diffForHumans(),
                ];
            });

            return AuthResult::success('تم جلب تقييمات الحلاق بنجاح', [
                'barber' => [
                    'id' => $barber->id,
                    'name' => $barber->name,
                ],
                'average_rating' => $averageRating,
                'total_ratings' => $totalRatings,
                'rating_distribution' => $ratingDistribution,
                'ratings' => $formattedRatings,
            ]);

        } catch (\Exception $e) {
            Log::error('Get barber ratings error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب التقييمات', $e->getMessage(), 500);
        }
    }

    /**
     * جلب تقييمات الصالون
     */
    public function getSalonRatings(int $salonId): AuthResult
    {
        try {
            $salon = Salon::find($salonId);

            if (!$salon) {
                return AuthResult::error('الصالون غير موجود', null, 404);
            }

            $ratings = Rating::where('salon_id', $salonId)
                ->where('is_approved', true)
                ->with('customer')
                ->orderBy('created_at', 'desc')
                ->get();

            $averageRating = $ratings->count() > 0 ? round($ratings->avg('rating'), 1) : 0;
            $totalRatings = $ratings->count();

            $ratingDistribution = [
                5 => $ratings->where('rating', 5)->count(),
                4 => $ratings->where('rating', 4)->count(),
                3 => $ratings->where('rating', 3)->count(),
                2 => $ratings->where('rating', 2)->count(),
                1 => $ratings->where('rating', 1)->count(),
            ];

            $formattedRatings = $ratings->map(function ($rating) {
                return [
                    'id' => $rating->id,
                    'customer_name' => $rating->customer->name,
                    'rating' => $rating->rating,
                    'comment' => $rating->comment,
                    'created_at' => $rating->created_at->diffForHumans(),
                ];
            });

            return AuthResult::success('تم جلب تقييمات الصالون بنجاح', [
                'salon' => [
                    'id' => $salon->id,
                    'name' => $salon->name,
                ],
                'average_rating' => $averageRating,
                'total_ratings' => $totalRatings,
                'rating_distribution' => $ratingDistribution,
                'ratings' => $formattedRatings,
            ]);

        } catch (\Exception $e) {
            Log::error('Get salon ratings error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب التقييمات', $e->getMessage(), 500);
        }
    }

    /**
     * جلب تقييمات الزبون (تقييماته التي كتبها)
     */
    public function getMyRatings(): AuthResult
    {
        try {
            $customer = auth()->user();

            $ratings = Rating::where('customer_id', $customer->id)
                ->with(['barber', 'salon', 'appointment'])
                ->orderBy('created_at', 'desc')
                ->get();

            $formattedRatings = $ratings->map(function ($rating) {
                return [
                    'id' => $rating->id,
                    'type' => $rating->barber_id ? 'barber' : 'salon',
                    'barber_name' => $rating->barber?->name,
                    'salon_name' => $rating->salon?->name,
                    'rating' => $rating->rating,
                    'comment' => $rating->comment,
                    'appointment_date' => $rating->appointment?->appointment_date,
                    'created_at' => $rating->created_at->diffForHumans(),
                ];
            });

            return AuthResult::success('تم جلب تقييماتك بنجاح', $formattedRatings);

        } catch (\Exception $e) {
            Log::error('Get my ratings error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب تقييماتك', $e->getMessage(), 500);
        }
    }
}
