<?php

namespace App\Http\Controllers\Vendor;

use App\Contracts\Repositories\VendorWalletRepositoryInterface;
use App\Contracts\Repositories\WithdrawRequestRepositoryInterface;
use App\Enums\ViewPaths\Vendor\Withdraw;
use App\Http\Controllers\BaseController;
use App\Services\VendorWalletService;
use Illuminate\Database\Eloquent\Collection;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class WithdrawController extends BaseController
{
    /**
     * @param WithdrawRequestRepositoryInterface $withdrawRequestRepo
     * @param VendorWalletRepositoryInterface $vendorWalletRepo
     * @param VendorWalletService $vendorWalletService
     */
    public function __construct(
        private readonly WithdrawRequestRepositoryInterface $withdrawRequestRepo,
        private readonly VendorWalletRepositoryInterface $vendorWalletRepo,
        private readonly VendorWalletService $vendorWalletService,
    )
    {

    }

    /**
     * @param Request|null $request
     * @param string|null $type
     * @return View|Collection|LengthAwarePaginator|callable|null
     */
    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable
    {
        return $this->getListView();
    }

    /**
     * @return View
     */
    public function getListView(): View
    {
        $vendorId = auth('seller')->id();
        $withdrawRequests = $this->withdrawRequestRepo->getListWhere(
            orderBy: ['id'=>'desc'],
            filters: ['vendorId' => $vendorId],
            relations: ['seller'],
            dataLimit: getWebConfig('pagination_limit')
        );
        return view(Withdraw::INDEX[VIEW], compact('withdrawRequests'));
    }
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getListByStatus(Request $request):JsonResponse
    {
        $vendorId = auth('seller')->id();
        $withdrawRequests = $this->withdrawRequestRepo->getListWhere(
            filters: [
                'vendorId' => $vendorId,
                'status' => $request['status']
            ],
            relations: ['seller'],
            dataLimit: getWebConfig('pagination_limit')
        );
        return response()->json([
            'view' => view(Withdraw::INDEX[TABLE_VIEW], compact('withdrawRequests'))->render(),
            'count' => $withdrawRequests->count(),
        ], 200);
    }

    /**
     * @param string|int $id
     * @return RedirectResponse
     */
    public function closeWithdrawRequest(string|int $id):RedirectResponse
    {
        $withdrawRequest = $this->withdrawRequestRepo->getFirstWhere(params: ['id' => $id]);
        $wallet = $this->vendorWalletRepo->getFirstWhere(params: ['seller_id' => auth('seller')->id()]);
        if ($withdrawRequest['approved'] == 0) {
            $totalEarning = $wallet['total_earning'] + currencyConverter($withdrawRequest['amount']);
            $pendingWithdraw = $wallet['pending_withdraw'] - currencyConverter($withdrawRequest['amount']);
            $this->vendorWalletRepo->update(
                id: $wallet['id'],
                data: $this->vendorWalletService->getVendorWalletData(
                    totalEarning: $totalEarning,
                    pendingWithdraw: $pendingWithdraw
                )
            );
            $this->withdrawRequestRepo->delete(['id' => $withdrawRequest['id']]);
            Toastr::success(message: translate('request_closed') . '!');
        } else {
            Toastr::error(message: translate('invalid_request'));
        }
        return redirect()->back();
    }

}
