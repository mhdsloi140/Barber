<?php
// app/Http/Controllers/API/Salon/ProfileController.php

namespace App\Http\Controllers\API\Salon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Salon\UpdateSalonRequest;
use App\Models\Salon;
use App\Services\Salon\UpdateSalonService;
use Illuminate\Http\Request;

class ProfileSalonController extends Controller
{
    public function __construct(
        private UpdateSalonService $updateSalonService
    ) {}

    /**
     * عرض بيانات الصالون الشخصية
     * GET /api/salon/profile
     */
    public function show()
    {
        $user = auth()->user();
        $salon = $user->ownedSalon;

        if (!$salon) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد صالون تابع لك'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'salon' => [
                    'id' => $salon->id,
                    'name' => $salon->name,
                    'address' => $salon->address,
                    'phone' => $salon->phone,
                    'latitude' => $salon->latitude,
                    'longitude' => $salon->longitude,
                    'images' => $salon->getImagesUrlsAttribute(),
                    'working_hours' => $this->getWorkingHoursFormatted($salon),
                ],
            ]
        ]);
    }

    /**
     * تحديث بيانات الصالون الشخصية
     * PUT /api/salon/profile
     */
    public function update(UpdateSalonRequest $request)
    {
        $result = $this->updateSalonService->updateSalon($request->validated());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * تنسيق أوقات العمل للعرض
     */
    private function getWorkingHoursFormatted(Salon $salon): array
    {
        $daysInArabic = [
            'sunday' => 'الأحد',
            'monday' => 'الإثنين',
            'tuesday' => 'الثلاثاء',
            'wednesday' => 'الأربعاء',
            'thursday' => 'الخميس',
            'friday' => 'الجمعة',
            'saturday' => 'السبت',
        ];

        $workingHours = $salon->workingHours()
            ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
            ->get();

        $result = [];
        foreach ($workingHours as $hour) {
            $result[] = [
                'day' => $hour->day_of_week,
                'day_ar' => $daysInArabic[$hour->day_of_week],
                'is_open' => (bool) $hour->is_open,
                'hours' => $this->getHoursText($hour),
            ];
        }
        return $result;
    }

    private function getHoursText($hour): string
    {
        if (!$hour->is_open) {
            return 'مغلق';
        }

        $hours = [];
        if ($hour->shift1_start && $hour->shift1_end) {
            $hours[] = $hour->shift1_start . ' - ' . $hour->shift1_end;
        }
        if ($hour->shift2_start && $hour->shift2_end) {
            $hours[] = $hour->shift2_start . ' - ' . $hour->shift2_end;
        }
        return implode(' و ', $hours);
    }
}
