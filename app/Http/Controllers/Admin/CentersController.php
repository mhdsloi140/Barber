<?php
// app/Http/Controllers/Admin/CentersController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Rating;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CentersController extends Controller
{
    /**
     * عرض جميع الصالونات
     */
    public function index(Request $request)
    {
        $query = Salon::with('owner');

        // فلترة البحث باسم الصالون
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        // فلترة حسب الحالة
        if ($request->filled('status')) {
            if ($request->status == 'active') {
                $query->where('is_active', true);
            } elseif ($request->status == 'inactive') {
                $query->where('is_active', false);
            }
        }

        // ترتيب حسب التاريخ
        $sortOrder = $request->get('sort', 'desc');
        $query->orderBy('created_at', $sortOrder);

        $salons = $query->paginate(10);
        $salons->appends($request->all());

        // إذا كان طلب AJAX، أعد JSON فقط
        if ($request->ajax() || $request->has('ajax')) {
            $html = view('admin.centers.table_rows', compact('salons'))->render();
            $pagination = $salons->links()->toHtml();

            return response()->json([
                'success' => true,
                'html' => $html,
                'pagination' => $pagination
            ]);
        }

        return view('admin.centers.index', compact('salons'));
    }

    /**
     * عرض تفاصيل صالون محدد
     */
  public function show($id)
{
    $salon = Salon::with(['owner', 'barbers'])->findOrFail($id);

    $totalAppointments = Appointment::where('salon_id', $salon->id)->count();
    $averageRating = Rating::where('salon_id', $salon->id)->avg('rating') ?? 0;
    $ratingsCount = Rating::where('salon_id', $salon->id)->count();


    $barbersCount = $salon->barbers()->count();


    $imagesCount = $salon->getMedia('salon_images')->count();

    return view('admin.centers.show', compact(
        'salon',
        'totalAppointments',
        'averageRating',
        'ratingsCount',
        'barbersCount',
        'imagesCount'
    ));
}

    /**
     * جلب بيانات الصالون بصيغة JSON (للموديل)
     */
    public function getSalonJson($id)
    {
        try {
            $salon = Salon::with('owner')->find($id);

            if (!$salon) {
                return response()->json([
                    'success' => false,
                    'message' => 'الصالون غير موجود'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'salon' => [
                    'id' => $salon->id,
                    'name' => $salon->name,
                    'owner' => $salon->owner ? ['name' => $salon->owner->name] : null,
                    'phone' => $salon->phone,
                    'address' => $salon->address,
                    'is_active' => $salon->is_active,
                    'barbers_count' => $salon->barbers()->count(),
                    'images_count' => $salon->getMedia('salon_images')->count(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('getSalonJson error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تفعيل الصالون
     */
  public function activate($id)
{
    try {
        $salon = Salon::findOrFail($id);

        Log::info('Activate salon called', [
            'id' => $id,
            'name' => $salon->name,
            'current_status' => $salon->is_active
        ]);


        $salon->is_active = true;
        $salon->save();
        $owner = $salon->owner;
        if ($owner) {
            $owner->is_active = true;
            $owner->save();


        }
        $barbers = $salon->barbers;
        $barbersActivated = 0;
        foreach ($barbers as $barber) {
            if (!$barber->is_active) {
                $barber->is_active = true;
                $barber->save();
                $barbersActivated++;
            }
        }

        if ($barbersActivated > 0) {
            Log::info('Barbers activated', [
                'salon_id' => $salon->id,
                'barbers_activated' => $barbersActivated
            ]);
        }

        // دعم طلبات AJAX
        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'تم تفعيل الصالون وصاحب الحساب بنجاح',
                'salon' => [
                    'id' => $salon->id,
                    'name' => $salon->name,
                    'is_active' => $salon->is_active
                ],
                'owner' => $owner ? [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'is_active' => $owner->is_active
                ] : null,
                'barbers_activated' => $barbersActivated
            ]);
        }

        $message = 'تم تفعيل الصالون وصاحب الحساب بنجاح';
        if ($barbersActivated > 0) {
            $message .= " وتم تفعيل {$barbersActivated} حلاق";
        }

        return redirect()->back()->with('success', $message);

    } catch (\Exception $e) {
        Log::error('Activate error: ' . $e->getMessage());

        if (request()->ajax()) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء التفعيل: ' . $e->getMessage()
            ], 500);
        }

        return redirect()->back()->with('error', 'حدث خطأ أثناء التفعيل');
    }
}

    /**
     * إيقاف الصالون
     */
  public function deactivate($id)
{
    try {
        $salon = Salon::findOrFail($id);

        Log::info('Deactivate salon called', [
            'id' => $id,
            'name' => $salon->name,
            'current_status' => $salon->is_active
        ]);


        $salon->is_active = false;
        $salon->save();



        $owner = $salon->owner;
        if ($owner) {
            $owner->is_active = false;
            $owner->save();

            Log::info('Salon owner deactivated', [
                'owner_id' => $owner->id,
                'owner_name' => $owner->name
            ]);
        }


        $barbers = $salon->barbers;
        $barbersDeactivated = 0;
        foreach ($barbers as $barber) {
            if ($barber->is_active) {
                $barber->is_active = false;
                $barber->save();
                $barbersDeactivated++;
            }
        }

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'تم إيقاف الصالون وصاحب الحساب بنجاح',
                'salon' => [
                    'id' => $salon->id,
                    'is_active' => $salon->is_active
                ]
            ]);
        }

        return redirect()->back()->with('success', 'تم إيقاف الصالون وصاحب الحساب بنجاح');

    } catch (\Exception $e) {
        Log::error('Deactivate error: ' . $e->getMessage());

        if (request()->ajax()) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الإيقاف: ' . $e->getMessage()
            ], 500);
        }

        return redirect()->back()->with('error', 'حدث خطأ أثناء الإيقاف');
    }
}

    /**
     * حذف الصالون
     */
 public function destroy($id)
{
    try {
        $salon = Salon::withTrashed()->findOrFail($id);
        $salonName = $salon->name;


        $owner = $salon->owner;
        $ownerId = $owner?->id;


        $barbers = $salon->barbers;
        $barbersCount = $barbers->count();

        $appointments = $salon->appointments;
        $appointmentsCount = $appointments->count();


        foreach ($appointments as $appointment) {
            $appointment->forceDelete();
        }


        $salon->ratings()->forceDelete();


        $salon->workingHours()->forceDelete();


        $salon->barbers()->detach();


        foreach ($barbers as $barber) {
            $barber->tokens()->delete();
            $barber->clearMediaCollection('avatar');
            $barber->forceDelete();
        }


        if ($owner) {
            $owner->tokens()->delete();
            $owner->clearMediaCollection('avatar');
            $owner->forceDelete();
        }


        $salon->clearMediaCollection('salon_images');


        $salon->forceDelete();

        Log::info('Salon and ALL associated data force deleted', [
            'salon_id' => $id,
            'salon_name' => $salonName,
            'owner_id' => $ownerId,
            'barbers_count' => $barbersCount,
            'appointments_count' => $appointmentsCount,
            'admin_id' => auth()->id()
        ]);

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'تم حذف الصالون وجميع البيانات المرتبطة به نهائياً'
            ]);
        }

        return redirect()->back()->with('success', 'تم حذف الصالون وجميع البيانات المرتبطة به نهائياً');

    } catch (\Exception $e) {
        Log::error('Force delete salon error: ' . $e->getMessage(), [
            'salon_id' => $id,
            'error' => $e->getMessage()
        ]);

        if (request()->ajax()) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف الصالون: ' . $e->getMessage()
            ], 500);
        }

        return redirect()->back()->with('error', 'حدث خطأ أثناء حذف الصالون');
    }
}
}
