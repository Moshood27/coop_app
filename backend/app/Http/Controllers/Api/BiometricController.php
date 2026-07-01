<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laragear\WebAuthn\Http\Requests\AttestationDeclarationRequest;
use Laragear\WebAuthn\Http\Requests\AssertionDeclarationRequest;
use Laragear\WebAuthn\Models\WebAuthnCredential;

class BiometricController extends Controller
{
    /**
     * Start the biometric registration process.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function registerOptions(Request $request)
    {
        return $request->user()->createRegisterOptions();
    }

    /**
     * Complete the biometric registration process.
     *
     * @param  \Laragear\WebAuthn\Http\Requests\AttestationDeclarationRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function registerVerify(AttestationDeclarationRequest $request)
    {
        $request->user()->addCredential($request);

        return response()->json(['message' => 'Biometric credential registered successfully']);
    }

    /**
     * Check if the user has biometrics registered.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function status(Request $request)
    {
        return response()->json([
            'has_biometrics' => $request->user()->webauthnCredentials()->exists()
        ]);
    }

    /**
     * Remove all biometric credentials.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function delete(Request $request)
    {
        $request->user()->webauthnCredentials()->delete();
        return response()->json(['message' => 'Biometric credentials removed']);
    }
}
