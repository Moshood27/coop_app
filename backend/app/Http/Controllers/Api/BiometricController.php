<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;
use Laragear\WebAuthn\Models\WebAuthnCredential;

class BiometricController extends Controller
{
    /**
     * Start the biometric registration process.
     *
     * @param  \Laragear\WebAuthn\Http\Requests\AttestationRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function registerOptions(AttestationRequest $request)
    {
        return $request->toCreate();
    }

    /**
     * Complete the biometric registration process.
     *
     * @param  \Laragear\WebAuthn\Http\Requests\AttestedRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function registerVerify(AttestedRequest $request)
    {
        $request->save();

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
            'has_biometrics' => $request->user()->webauthnCredentials()->exists() || !empty($request->user()->biometric_template)
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
        $request->user()->update(['biometric_template' => null]);
        return response()->json(['message' => 'Biometric credentials removed']);
    }

    /**
     * Save USB biometric template.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function saveUsbTemplate(Request $request)
    {
        $request->validate([
            'template' => 'required|string',
        ]);

        $request->user()->update([
            'biometric_template' => $request->template,
        ]);

        return response()->json(['message' => 'USB Biometric template saved successfully']);
    }
}
