<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PassbookService;
use Illuminate\Http\Request;

class PassbookController extends Controller
{
    public function getMatrix(Request $request, int $year, PassbookService $passbookService)
    {
        $data = $passbookService->getPassbookData($request->user(), $year);

        return response()->json($data);
    }

    public function contributions(Request $request)
    {
        $contributions = $request->user()->contributions()
            ->with('scheme')
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($contributions);
    }
}
