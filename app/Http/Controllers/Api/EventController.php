<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventController extends Controller
{
    public function getEvents(Request $request)
    {
        try {
            $user = $request->user();
            $events = Event::where('user_id', $user->id)
                ->where('status', '=', 1)
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($events as $event) {
                if ($event->photo !== null) {
                    $event->photo = url(Storage::url($event->photo));
                }
            }

            return response()->json([
                'success' => true,
                'data' => $events
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'trace' => $th->getTrace()
            ]);
        }
    }

    public function getEvent(Request $request, $slug)
    {
        try {
            $user = $request->user();
            $event = $user->events()->where('slug', $slug)
                ->where('status', '=', 1)
                ->first();

            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'El evento no existe.',
                ]);
            }

            if ($event->photo !== null) {
                $event->photo = url(Storage::url($event->photo));
            }

            return response()->json([
                'success' => true,
                'data' => $event
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'trace' => $th->getTrace()
            ]);
        }
    }

    public function createEvent(Request $request)
    {
        try {
            $user = $request->user();

            $validate = Validator::make($request->all(), [
                'title' => ['required', 'max:255'],
                'description' => ['nullable', 'max:255'],
                'start' => ['required', 'date'],
                'end' => ['required', 'date'],
                'priority' => ['required'],
                'location' => ['nullable', 'max:350'],
                'photo' => ['nullable', 'image'],
                'color' => ['nullable', 'max:100'],
            ]);

            if ($validate->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación.',
                    'errors' => $validate->errors(),
                ]);
            }

            $input = $request->all();
            $input['slug'] = $this->createUniqueSlug($input['title'], $user->id);
            $input['user_id'] = $user->id;
            $input['status'] = 1;

            if ($request->hasFile('photo')) {
                Storage::makeDirectory('public/events');

                $path = Storage::put('public/events/' . uniqid(), $request->file('photo'));
                $input['photo'] = $path;
            }

            $event = Event::create($input);

            if ($event) {
                return response()->json([
                    'success' => true,
                    'message' => 'Evento creado exitosamente.',
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al crear el evento, por favor intente nuevamente.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'trace' => $th->getTrace()
            ]);
        }
    }

    public function updateEvent(Request $request, $slug)
    {
        try {
            $user = $request->user();
            $event = Event::where('slug', $slug)
                ->where('user_id', $user->id)
                ->first();

            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'El evento no existe o no tienes permiso para editarlo.',
                ]);
            }

            $validate = Validator::make($request->all(), [
                'title' => ['required', 'max:255'],
                'description' => ['max:255'],
                'start' => ['required', 'date'],
                'end' => ['required', 'date'],
                'priority' => ['required'],
                'location' => ['max:350'],
                'color' => ['max:100'],
            ]);

            if ($validate->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación.',
                    'errors' => $validate->errors(),
                ]);
            }

            $input = $request->all();
            $input['slug'] = $this->createUniqueSlug($input['title'], $user->id);

            if ($request->hasFile('photo')) {
                if ($event->photo) {
                    Storage::delete($event->photo);

                    $directory = dirname($event->photo);

                    if (Storage::files($directory) === []) {
                        Storage::deleteDirectory($directory);
                    }
                }

                Storage::makeDirectory('public/events');

                $path = Storage::put('public/events/' . uniqid(), $request->file('photo'));
                $input['photo'] = $path;
            }

            $event->update($input);

            return response()->json([
                'success' => true,
                'message' => 'Evento actualizado exitosamente.',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'trace' => $th->getTrace()
            ]);
        }
    }

    public function deleteEvent(Request $request, $slug)
    {
        try {
            $user = $request->user();
            $event = Event::where('slug', $slug)
                ->where('user_id', $user->id)
                ->first();

            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'El evento no existe o no tienes permiso para eliminarlo.',
                ]);
            }

            $event->status = 0;
            $event->save();

            return response()->json([
                'success' => true,
                'message' => 'Evento eliminado exitosamente.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'trace' => $th->getTrace()
            ]);
        }
    }

    public function createUniqueSlug($title, $userId)
    {
        $slug = Str::slug($title);

        $existingSlug = Event::where('slug', $slug)
            ->where('user_id', $userId)
            ->first();
        $suffix = 1;

        while ($existingSlug) {
            $slug = $slug . '-' . $suffix;
            $existingSlug = Event::where('slug', $slug)
                ->where('user_id', $userId)
                ->first();
            $suffix++;
        }

        $maxLength = 100;
        if (strlen($slug) > $maxLength) {
            $slug = substr($slug, 0, $maxLength);
        }

        return $slug;
    }
}
