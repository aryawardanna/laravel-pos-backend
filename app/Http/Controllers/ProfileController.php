<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    // edit
    public function edit()
    {
        /** @var User $user */
        $user = Auth::user();

        return view('pages.profile.edit', [
            'user' => $user,
        ]);
    }

    // update
    public function update(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // validasi
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            // Password boleh dikosongkan jika tidak ingin diubah
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // simpan data
        $user->name = $request->name;
        $user->email = $request->email;
        $user->updated_by = $user->id;
        $user->save();

        if ($request->password) {
            $user->password = Hash::make($request->password);
            $user->save();
        }

        return redirect()->route('profile.edit')->with('success', 'Profil berhasil diperbarui');
    }
}
