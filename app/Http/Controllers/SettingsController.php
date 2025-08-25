<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Return settings as JSON for the frontend modal.
     */
    public function index()
    {
        return response()->json([
            'rename_on_apply' => (bool) setting('rename_on_apply', false),
        ]);
    }

    /**
     * Update settings from the modal form.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'rename_on_apply' => ['nullable', 'boolean'],
        ]);

        // Coerce to boolean with default false when missing
        $rename = (bool) data_get($data, 'rename_on_apply', false);
        setting(['rename_on_apply' => $rename]);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Settings updated']);
        }

        return back()->with('success', 'Settings updated');
    }
}
