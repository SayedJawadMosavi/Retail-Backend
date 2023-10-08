<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\FileTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    use FileTrait;

    public $path = "images/users/profile";
    public function __construct()
    {
        $this->middleware('permissions:user_view')->only('index');
        $this->middleware('permissions:user_create')->only(['store']);
        $this->middleware('permissions:user_force_delete')->only(['forceDelete']);
        $this->middleware('permissions:user_delete')->only(['destroy']);
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $query = new User();
            $searchCol = ['name', 'email', 'role', 'created_at'];

            $query = $this->search($query, $request, $searchCol);
            $trashTotal = clone $query;
            $trashTotal = $trashTotal->onlyTrashed()->count();

            $allTotal = clone $query;
            $allTotal = $allTotal->count();
            if ($request->tab == 'trash') {
                $query = $query->onlyTrashed();
            }
            $query = $query->latest()->paginate($request->itemPerPage);
            $results = $query->items();
            $total = $query->total();

            return response()->json(["data" => $results, 'total' => $total, "extraTotal" => ['users' => $allTotal, 'trash' => $trashTotal]]);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }






    public function store(Request $request)
    {
        try {
            $request->validate(
                [
                    'role' => ['required'],
                    'permissions' => ['required'],
                    'name' => 'required',
                    'email' => 'required|email|unique:users,email',
                    'password' => 'required|min:6',
                    'confirm_password' => 'required|min:6',

                ],
                [
                    'email.unique' => 'this email is used before!',
                    'role.required' =>'role is required',
                    'permissions.required' => 'Permission is required',
                    'email.required' => "Email is required",
                    'email.email' => "the email is incorrect",
                    'password.required' => 'password is required',
                    'confirm_password.required' => 'Confirm password is required',
                    'password.min' => 'password can not be less than one letter',
                    'confirm_password.min' => 'confirm password cant not be less than one letter',

                ]

            );
            DB::beginTransaction();


            $user = User::create([
                "name" => $request->name,
                "email" => $request->email,
                "role" => $request->role,
                "permissions" => json_encode($request->permissions),
                "password" => bcrypt($request->password),
            ]);

            DB::commit();
            return response()->json(['result' => true, 'user' => $user], 201);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
    }

    public function update(Request $request, $id)
    {
        $request->validate(
            [
                'name' => 'required',
                'email' => 'required|email|unique:users,email,' . Auth::user()->id,
            ],
            ['name.required' => 'name is required!']

        );
        try {
            DB::beginTransaction();
            $user = User::find(Auth::user()->id);
            if ($request->hasFile('profile')) {
                $file =  $request->file('profile');
                $imagePath  = $this->storeAndRemove($request->file('profile'),  $user->getRawOriginal("profile"), $this->path);
                $user->update(['profile' => $imagePath]);
            }
            $user->update(['name' => $request->name, 'email' => $request->email]);
            DB::commit();
            return  response()->json($user, 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json($e->getMessage(), 500);
        }
    }


    public function editUser(Request $request)
    {
        $request->validate(
            [
                'id' => 'required|unique:users,id,' . $request->id,
                'role' => ['required'],
                'permissions' => ['required'],
                'name' => 'required',
                'email' => 'required|email|unique:users,email,' . $request->id,
            ],
            [
               

                'email.unique' => 'this email is used before!',
                'role.required' =>'role is required',
                'permissions.required' => 'Permission is required',
                'email.required' => "Email is required",
                'email.email' => "the email is incorrect",
            ]
        );
        try {
            DB::beginTransaction();
            $user = User::find($request->id);
            $user->update([
                "name" => $request->name,
                "email" => $request->email,
                "role" => $request->role,
                "permissions" => json_encode($request->permissions),
            ]);

            $targetUser = User::find($request->id);

            // Retrieve all of the user's tokens
            $tokens = $targetUser->tokens;
            // Loop through the tokens and delete the one you want to remove
            foreach ($tokens as $token) {
                $token->delete();
            }
            DB::commit();
            return  response()->json($user, 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $ids = explode(",", $id);
            $result =  User::whereIn('id', $ids)->delete();

            DB::commit();
            return  response()->json($result, 206);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json($e->getMessage(), 500);
        }
    }
    public function restore(string $id)
    {
        try {
            $ids = explode(",", $id);
            User::whereIn('id', $ids)->withTrashed()->restore();
            return response()->json(true, 203);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
    public function forceDelete(string $id)
    {
        try {
            $ids = explode(",", $id);
            User::whereIn('id', $ids)->withTrashed()->forceDelete();
            return response()->json(true, 203);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }


    public function changePassword(Request $request)
    {

        // passwordPayload.value.newPassword = null
        // passwordPayload.value.current_password = null
        // passwordPayload.value.confirm_password = null
        // "password" => bcrypt($request->password),
        // return $request->all();
        try {
            $id = Auth::user()->id;
            $user = User::find($id);
            $user->password = bcrypt($request->newPassword);
            $user->save();
            return response()->json(true, 202);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
}
