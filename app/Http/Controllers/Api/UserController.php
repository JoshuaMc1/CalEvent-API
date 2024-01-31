<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function login(Request $request)
    {
        try {
            $validate = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required'],
            ]);

            $user = User::where('email', $request->input('email'))->first();

            if (!$user || $user->status === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no existe o no está activo.',
                ]);
            }

            if (Auth::attempt($validate)) {
                return response()->json([
                    'success' => true,
                    'token' => Auth::user()->createToken('API TOKEN', ['*'], now()->addDays(31))->plainTextToken,
                    'message' => 'Bienvenido, ' . Auth::user()->profile->name . ' ' . Auth::user()->profile->surname . '.',
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'El usuario o contraseña son incorrectos.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'trace' => $th->getTrace(),
            ]);
        }
    }

    public function register(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'email' => ['required', 'email'],
                'password' => ['required',],
                'password_confirmation' => ['required', 'same:password'],
                'name' => ['required', 'max:100'],
                'surname' => ['required', 'max:100'],
            ]);

            if ($validate->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación.',
                    'errors' => $validate->errors(),
                ]);
            }

            $user = User::where('email', $request->input('email'))->first();

            if ($user) {
                return response()->json([
                    'success' => false,
                    'message' => 'El correo electrónico ya esta registrado, por favor intente nuevamente con un correo electrónico diferente.',
                ]);
            }

            $input = $request->all();
            $input['password'] = Hash::make($input['password']);
            $input['status'] = 1;

            $user = User::create($input);

            $profile = Profile::create([
                'user_id' => $user->id,
                'name' => $input['name'],
                'surname' => $input['surname'],
            ]);

            if ($user && $profile) {
                return response()->json([
                    'success' => true,
                    'message' => 'Registro exitoso.',
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al crear el usuario, por favor intente nuevamente.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'trace' => $th->getTrace(),
            ]);
        }
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            $user->tokens()->delete();
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Sesión cerrada exitosamente.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'trace' => $th->getTrace(),
            ]);
        }
    }

    public function changePassword(Request $request)
    {
        try {
            $user = $request->user();
            $validate = Validator::make($request->all(), [
                'current_password' => ['required'],
                'password' => ['required'],
                'password_confirmation' => ['required', 'same:password'],
            ]);

            if ($validate->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación.',
                    'errors' => $validate->errors(),
                ]);
            }

            if (empty($request->input('current_password'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Por favor, ingrese la contraseña actual.',
                ]);
            }

            if (!Hash::check($request->input('current_password'), $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La contraseña actual es incorrecta.',
                ]);
            }

            $user->password = Hash::make($request->input('password'));
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Contraseña actualizada exitosamente.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'trace' => $th->getTrace()
            ]);
        }
    }

    public function me(Request $request)
    {
        try {
            $user = $request->user();
            $user->profile;

            if ($user->status === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no existe o no esta activo.',
                ]);
            }

            if ($user->profile->photo !== null) {
                $user->profile->photo = url(Storage::url($user->profile->photo));
            }

            return response()->json([
                'success' => true,
                'data' =>  $user,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'trace' => $th->getTrace(),
            ]);
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();
            $profile = Profile::where('user_id', $user->id)->first();

            $validate = Validator::make($request->all(), [
                'name' => ['required', 'max:100'],
                'surname' => ['required', 'max:100'],
            ]);

            if ($validate->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación.',
                    'errors' => $validate->errors(),
                ]);
            }

            if ($request->hasFile('photo')) {
                if ($profile->photo) {
                    Storage::delete($profile->photo);

                    $directory = dirname($profile->photo);

                    if (Storage::files($directory) === []) {
                        Storage::deleteDirectory($directory);
                    }
                }

                $photo = $request->file('photo');

                Storage::makeDirectory('public/profiles');
                $path = Storage::put('public/profiles/' . uniqid(), $photo);
                $profile->photo = $path;
            }

            $input = $request->all();
            $profile->name = $input['name'];
            $profile->surname = $input['surname'];
            $profile->save();

            return response()->json([
                'success' => true,
                'message' => 'Perfil actualizado exitosamente.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'trace' => $th->getTrace()
            ]);
        }
    }

    public function disableUser(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->status === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no existe o no esta activo.',
                ]);
            }

            $user->status = 0;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Usuario desactivado exitosamente.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'trace' => $th->getTrace()
            ]);
        }
    }

    public function deleteUser(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->status === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no existe o no esta activo.',
                ]);
            }

            $user->tokens()->delete();
            $user->events()->delete();
            $user->profile()->delete();
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Usuario eliminado exitosamente.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'trace' => $th->getTrace()
            ]);
        }
    }
}
