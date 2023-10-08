<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
    public function validationTranslation()
    {
        # code...
        return [
         
            "created_at.date" => "register date is not correct",
            "created_at.before_or_equal" => "register date can not be greater than now!",
            "date.required" => "date is required",
            "date.before_or_equal" => "register date can not be greater than now!",
            'name.required' => 'name is required',
            'name.min' => 'name can not be less than three letter',
         
            'customer_name.required' => 'customer is required',
            'customer_phone.required' => 'phone number is required',
      
            'address.required' => 'address is required',
          
      
            'paid_amount.numeric' => 'paid amount must be number',
            'paid_amount.min' => 'paid amount can not be less than one',
      
        ];
    }

    public function search($query, $request, $columns)
    {

        $searchBy = $request->searchBy;
        $search = $request->search;
        if ($searchBy && $search != '') {
            if ($searchBy == 'all') {
                foreach ($columns as $key => $value) {
                    $variables = explode('.', $value);
                    if (count($variables) > 1) {
                        if ($key == 0) {
                            $query =   $query->whereHas($variables[0], function ($q) use ($variables, $search) {
                                return $q->where($variables[1], 'LIKE', '%' . $search . '%');
                            });
                        } else {

                            $query =   $query->orWhereHas($variables[0], function ($q) use ($variables, $search) {
                                return $q->where($variables[1], 'LIKE', '%' . $search . '%');
                            });
                        }
                    } else {
                        if ($key == 0)
                            $query =  $query->where($value, 'LIKE', '%' . $search . '%');
                        else
                            $query =  $query->orWhere($value, 'LIKE', '%' . $search . '%');
                    }
                }
            } else {
                if ($searchBy == 'created_at') {
                    $query =  $query->where('created_at', 'LIKE', '%' . $search . '%');
                }

                $query =   $query->where($searchBy, $search);
            }
        }

        return $query;
    }
}
