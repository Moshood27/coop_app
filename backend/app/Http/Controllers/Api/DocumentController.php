<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /**
     * Serve a protected document from the private storage.
     *
     * @param Request $request
     * @param string $path
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function serve(Request $request, string $path)
    {
        // Ensure the path is safe (prevent directory traversal)
        if (str_contains($path, '..')) {
            abort(403, 'Invalid path.');
        }

        if (!Storage::disk('local')->exists($path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('local')->response($path);
    }

    /**
     * Serve a document to the authenticated member, ensuring they own it.
     */
    public function serveMember(Request $request, string $path)
    {
        $user = $request->user();

        // Check if the path belongs to the user
        $isOwner = ($user->passport_path === $path || $user->nursing_mother_proof_path === $path);

        if (!$isOwner) {
            // Also check member applications associated with this user
            $isOwner = \App\Models\MemberApplication::where('user_id', $user->id)
                ->where(function ($query) use ($path) {
                    $query->where('passport_path', $path)
                        ->orWhere('id_card_path', $path)
                        ->orWhere('proof_of_address_path', $path)
                        ->orWhere('guarantor_signature_path', $path)
                        ->orWhere('imam_signature_path', $path)
                        ->orWhere('spouse_father_consent_signature_path', $path);
                })->exists();
        }

        if (!$isOwner) {
            abort(403, 'Unauthorized access to document.');
        }

        return $this->serve($request, $path);
    }
}
