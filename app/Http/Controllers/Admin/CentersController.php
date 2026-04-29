<?php
// app/Http/Controllers/Admin/CentersController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Http\Request;

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

        return view('admin.centers.show', compact('salon'));
    }
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
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function activate($id)
    {
        $salon = Salon::findOrFail($id);
        $salon->is_active = true;
        $salon->save();

        return redirect()->back()->with('success', 'تم تفعيل الصالون بنجاح');
    }
    public function deactivate($id)
    {
        $salon = Salon::findOrFail($id);
        $salon->is_active = false;
        $salon->save();

        return redirect()->back()->with('success', 'تم إيقاف الصالون بنجاح');
    }
    // في CentersController.php - نسخة مع return JSON
public function destroy($id)
{
    try {
        $salon = Salon::findOrFail($id);
        $salon->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الصالون بنجاح'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}
}
