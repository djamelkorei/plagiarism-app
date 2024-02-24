<?php

namespace App\Helpers;

use App\Models\Enums\BalanceLineStatus;
use App\Models\Transaction;
use Carbon\Carbon;
use Exception;

/**
 * Class DataTableHelper
 * @package App\Helpers
 * @author Djamel Eddine Korei
 */
class DataTableHelper
{

    /**
     * generate email column for datatable
     * @param $column
     * @param $attributeName
     * @return string
     */
    public static function getEmailColumn($column, $attributeName = 'email')
    {
        if (isset($column)) {
            if ($column[$attributeName]) {
                return "<a class='btn btn-outline-dark btn-sm' href='mailto:" . $column[$attributeName] . "'><i class='fa fa-envelope me-1'></i>" . $column[$attributeName] . "</a>";
            } else {
                return '';
            }
        }
    }

    /**
     * generate phone column for datatable
     * @param $column
     * @param $attributeName
     * @return string
     */
    public static function getPhoneColumn($column, $attributeName = 'phone')
    {
        if (isset($column)) {
            if ($column[$attributeName]) {
                return "<button class='btn btn-outline-dark btn-sm'><i class='fa fa-phone-alt mr-1'></i>" . $column[$attributeName] . "</button>";
            } else {
                return '';
            }
        }
    }

    /**
     * generate active column for datatable
     * @param $column
     * @param $attributeName
     * @return string
     */
    public static function getActiveColumn($column, $attributeName = 'active', $check = 'active')
    {
        if (isset($column)) {
            if ($column[$attributeName] == $check) {
                return "<span class='badge text-bg-primary shadow p-1 rounded-1'>active</i></span>";
            } else {
                return "<span class='badge text-bg-danger shadow p-1 rounded-1'>inactive</span>";
            }
        }
    }

    /**
     * generate city column for datatable
     * @param $column
     * @param string $attributeName
     * @return string
     */
    public static function getCityColumn($column, string $attributeName = 'city')
    {
        if (isset($column)) {
            return "<i class='fa-solid fa-map-marker-alt text-secondary'></i> $column[$attributeName]";
        }
    }

    /**
     * generate role column for datatable
     * @param $column
     * @param string $attributeName
     * @return string
     */
    public static function getRoleColumn($column, string $attributeName = 'name')
    {
        if (isset($column)) {
            return "<span class='badge ". ($column[$attributeName] == 'super-admin' ? 'text-bg-secondary text-white' : 'text-bg-primary' )." shadow-sm p-1 rounded-1 small'><i class='fa-solid fa-shield'></i> $column[$attributeName]</span>";
        }
    }

    /**
     * generate date column for datatable
     * @param $modelObject
     * @param string $attributeName
     * @return string
     * @throws Exception
     */
    public static function getDateColumn($modelObject, $attributeName = 'updated_at')
    {
        return Carbon::parse($modelObject[$attributeName])->format('Y-m-d');
    }

    /**
     * generate date column for datatable
     * @param $modelObject
     * @param string $attributeName
     * @return string
     * @throws Exception
     */
    public static function getDateTimeColumn($modelObject, string $attributeName = 'updated_at'): string
    {
        return Carbon::parse($modelObject[$attributeName])->format('Y-m-d H:i');
    }

    /**
     * @param $user
     * @return string
     */
    public static function getUserColumn($user): string
    {
        return "
                 <div class='d-flex align-items-center gap-2'>
                        <img class='rounded-circle shadow'
                             width='32px'
                             height='32px'
                             src='https://ui-avatars.com/api/?background=random&amp;name=$user->name'
                             alt=''>
                        <div class='small'>
                             <h6 class='mb-0 fw-bold'>$user->name</h6>
                             <h6 class='mb-0 text-muted small'>$user->email</h6>
                        </div>
                 </div>
                ";
    }

    public static function getProductColumn($product, $license): string
    {
        $url = route('guest.templates.show', ['product' => $product->name ]);
        return "
                 <div class='d-flex align-items-center gap-3 align-items-center'>
                      <img class='img-fluid shadow rounded-2' width='80px' src='$product->image' alt='$product->name'>
                      <div>
                        <div class=' gap-2'>
                            <a href='$url' target='_blank' class='text-capitalize text-decoration-none fw-bold text-black'>$product->name</a>
                            <span class='badge badge-sm ". ( $license == 'free' ? "text-bg-primary" : "text-bg-primary" )  ."'>$license</span>
                        </div>
                        <p class='text-muted mb-0'>$product->description</p>
                      </div>
                 </div>
                ";
    }

    public static function getLicenseColumn($license): string
    {
        return "<span class='badge rounded-2 p-2 shadow ". ( $license == 'free' ? "bg-gray" : "text-bg-primary" )  ." fw-bold text-capitalize'>$license</span>";
    }

    public static function getProductStatus(Transaction $transaction): string
    {
        $status  = $transaction->getStatus();
        $color =  $status == BalanceLineStatus::PAID ? 'text-bg-primary' : 'text-bg-warning text-white';
        return "<span class='badge rounded-2 p-2 shadow small $color fw-bold text-capitalize'>$status</span>";
    }

}
