<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\FileTrait;
use Illuminate\Support\Facades\File;


class UserController extends Controller
{
    use FileTrait;

    public $path = "images/users/profile";

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $relations = ['roles'];
            $query = new User();
            $extra_data =   $this->getTabCount(clone $query, $request);
            if ($request->current_tab != 'all') {
                $query = $query->whereStatus($request->current_tab);
            }
            if ($request->search != "") {
                $query = $this->searchContent($query, $request);
            }
            $query = $query->with($relations)->paginate($request->itemsPerPage);
            $data = $query->items();
            $total = $query->total();

            $result = [
                "data" => $data,
                "total" => $total,
            ];
            $result['extra_data'] = $extra_data;
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 400);
        }
    }

    public function searchContent($query, $request)
    {
        $query =  $query->where('id', $request->search);
        $query = $query->orwhere(function ($q) use ($request) {
            return $q->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('lastname', 'like', '%' . $request->search . '%')
                ->orWhere('email', 'like', '%' . $request->search . '%');
        });
        
        return $query;
    }

    public function getTabCount($query, $request)
    {
        $extra_data = [];
        foreach ($request->tabs as $value) {
            # code...
            $subQuery = clone $query;
            if ($value == 'all') {
                $extra_data[$value] = $subQuery->get()->count();
            } else {
                $extra_data[$value] = $subQuery->where('status', $value)->count();
            }
        }
        return $extra_data;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $photo = '';
            if ($request->hasFile('profile')) {
                $imagePath  = $this->storeFile($request->file('profile'), $this->path);
                $photo = $imagePath;
            }

            $user = User::create([
                "name" => $request->name,
                "lastname" => $request->lastname,
                "email" => $request->email,
                "profile" => $photo,
                "password" => bcrypt($request->password),
            ]);

            $user->syncRoles($request->roles);
            // $user->sendEmailVerificationNotification();
            DB::commit();
            return response()->json(['result' => true, 'user' => $user], 201);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json(["result" => false, "message" => 'Something went wrong!', 'exceptionError' => $th->getMessage()], 400);
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
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $user = User::find($id);
            $inputs = $request->all();
            if ($request->hasFile('profile')) {
                $imagePath  = $this->storeAndRemove($request->file('profile'),  $user->getRawOriginal("profile"), $this->path);
                $user->update(['profile' => $imagePath]);
            }

            $user->update($inputs);
            DB::commit();
            return  response()->json(["result" => true, "status" => 200]);
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
            $user =  User::findOrfail($id);

            if ($user) {
                $this->deleteFile($user->getRawOriginal("profile"));
            }
            $user->delete();

            DB::commit();
            return  response()->json(["result" => true, "status" => 200, "message" => "Your post has been deleted!"]);
        } catch (\Exception $e) {
            DB::rollback();
            $array = array("error" => array('Something went wrong!'));
            return response()->json(["result" => false, "message" => $array, 'exceptionError' => $e->getMessage()]);
        }
    }
}
