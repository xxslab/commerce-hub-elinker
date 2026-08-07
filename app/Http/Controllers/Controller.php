<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected function company(): Company
    {
        $user = request()->user();

        abort_unless($user, 403);

        if ($user->company_id && ($company = Company::find($user->company_id))) {
            return $company;
        }

        $company = Company::create([
            'name' => $user->name ?: 'Company ' . $user->id,
            'email' => $user->email,
        ]);

        $user->forceFill(['company_id' => $company->id])->save();

        return $company;
    }
}
