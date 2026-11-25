<?php

namespace App\Http\Controllers\Admin;

use App\Enums\HttpStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionCollection;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        Permission::TRANSACTIONS->authorize();

        $this->validate($request, [
            'status' => ['nullable', 'in:pending,complete,rejected'],
            'method' => ['nullable', 'string', 'max:30'],
            'reference' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $transactions = Transaction::query()
            ->with('user')
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->input('status')))
            ->when($request->filled('method'), fn (Builder $query) => $query->where('method', $request->input('method')))
            ->when($request->filled('reference'), fn (Builder $query) => $query->where('reference', $request->input('reference')))
            ->latest('id')
            ->paginate($request->input('limit', 30));

        return (new TransactionCollection($transactions))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }
}
