<?php
// app/Http/Controllers/Admin/AdvertisementController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Advertisement\StoreAdvertisementRequest;
use App\Http\Requests\Admin\Advertisement\UpdateAdvertisementRequest;
use App\Models\Advertisement;
use App\Services\Admin\AdvertisementService;
use Illuminate\Http\Request;

class AdvertisementController extends Controller
{
    protected $advertisementService;

    public function __construct(AdvertisementService $advertisementService)
    {
        $this->advertisementService = $advertisementService;
    }

    public function index()
    {
        $ads = $this->advertisementService->getAllAdvertisements();
        return view('admin.ads.index', compact('ads'));
    }

    public function create()
    {
        return view('admin.ads.create');
    }

    public function store(StoreAdvertisementRequest $request)
    {
        $result = $this->advertisementService->createAdvertisement(
            $request->validated(),
            $request->file('images', [])
        );

        if (!$result['success']) {
            return back()->with('error', $result['message'])->withInput();
        }

        return redirect()->route('ads.index')
            ->with('success', $result['message']);
    }

    public function edit(Advertisement $ad)
    {
        $data = $this->advertisementService->getAdvertisementWithImages($ad);
        return view('admin.ads.edit', $data);
    }

    public function update(UpdateAdvertisementRequest $request, Advertisement $ad)
    {
        $result = $this->advertisementService->updateAdvertisement(
            $ad,
            $request->validated(),
            $request->file('images', []),
            $request->input('delete_images', [])
        );

        if (!$result['success']) {
            return back()->with('error', $result['message'])->withInput();
        }

        return redirect()->route('ads.index')
            ->with('success', $result['message']);
    }

    public function destroy($id)
    {
        $advertisement = Advertisement::findOrFail($id);

        $result = $this->advertisementService->deleteAdvertisement($advertisement);

        return response()->json($result);
    }

    public function updateOrder(Request $request)
    {
        $result = $this->advertisementService->updateOrder($request->input('orders', []));

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    public function toggleStatus(Advertisement $ad)
    {
        $result = $this->advertisementService->toggleStatus($ad);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    public function duplicate(Advertisement $ad)
    {
        $result = $this->advertisementService->duplicateAdvertisement($ad);

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return redirect()->route('ads.index')
            ->with('success', $result['message']);
    }
    public function getJson(Advertisement $ad)
    {
        $images = $ad->getMedia('ad_images')->map(function ($image) {
            return [
                'id' => $image->id,
                'url' => $image->getUrl(),
                'thumb' => $image->getUrl('thumb'),
                'name' => $image->file_name
            ];
        });

        return response()->json([
            'success' => true,
            'ad' => [
                'id' => $ad->id,
                'title' => $ad->title,
                'description' => $ad->description,
                'link_url' => $ad->link_url,
                'is_active' => $ad->is_active,
                'starts_at' => $ad->starts_at,
                'ends_at' => $ad->ends_at,
                'images' => $images
            ]
        ]);
    }
}
