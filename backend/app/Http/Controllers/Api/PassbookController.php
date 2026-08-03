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
}
