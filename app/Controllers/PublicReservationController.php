<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\TenantContext;
use App\Services\PublicReservationService;
use InvalidArgumentException;
use Throwable;

final class PublicReservationController
{
    public function show(Request $request, array $params): void
    {
        $token = strtolower(trim((string)($params['token'] ?? '')));
        $restaurant = PublicReservationService::getRestaurantByToken($token);
        if (!$restaurant) Response::notFound('This reservation booking link is invalid or no longer active.');
        Response::render('public_reservations/book', [
            'restaurant' => $restaurant, 'token' => $token,
            'csrf_token' => CSRF::getToken(), 'error' => trim((string)$request->get('error','')),
        ], 'none');
    }

    public function availability(Request $request, array $params): void
    {
        $token = strtolower(trim((string)($params['token'] ?? '')));
        $date = trim((string)$request->get('date',''));
        $party = filter_var($request->get('party_size',2), FILTER_VALIDATE_INT);
        try {
            if (!is_int($party) || $party<1 || $party>30) throw new InvalidArgumentException('Choose a valid party size.');
            Response::json(['slots'=>PublicReservationService::availability($token,$date,$party)]);
        } catch (InvalidArgumentException $e) {
            Response::json(['slots'=>[],'error'=>$e->getMessage()],422);
        } catch (Throwable $e) {
            error_log('[Public Reservation Availability] '.$e->getMessage());
            Response::json(['slots'=>[],'error'=>'Availability could not be loaded. Please try again.'],500);
        }
    }

    public function create(Request $request, array $params): void
    {
        $token = strtolower(trim((string)($params['token'] ?? '')));
        if (!CSRF::validate($request->csrfToken())) {
            Response::redirect('/reserve/'.rawurlencode($token).'?error='.rawurlencode('Your session expired. Please refresh the page and try again.'));
        }
        try {
            $booking = PublicReservationService::createPublic($token,$request->all());
            Response::redirect('/reserve/'.rawurlencode($token).'/status/'.rawurlencode($booking['tracking_token']));
        } catch (InvalidArgumentException $e) {
            Response::redirect('/reserve/'.rawurlencode($token).'?error='.rawurlencode($e->getMessage()));
        } catch (Throwable $e) {
            error_log('[Public Reservation Create] '.$e->getMessage());
            Response::redirect('/reserve/'.rawurlencode($token).'?error='.rawurlencode('We could not submit your reservation. Please try again or contact the restaurant.'));
        }
    }

    public function status(Request $request, array $params): void
    {
        $token=strtolower(trim((string)($params['token']??'')));
        $tracking=strtolower(trim((string)($params['trackingToken']??'')));
        $restaurant=PublicReservationService::getRestaurantByToken($token);
        $reservation=PublicReservationService::getPublicStatus($token,$tracking);
        if(!$restaurant || !$reservation) Response::notFound('This reservation status link is invalid or no longer active.');
        Response::render('public_reservations/status',['restaurant'=>$restaurant,'reservation'=>$reservation,'paramsToken'=>$token],'none');
    }

    public function settings(Request $request, array $params=[]): void
    {
        $restaurantId=$this->authorizeOwner();
        PublicReservationService::ensureSchema();
        $token=PublicReservationService::getOrCreateLink($restaurantId);
        Response::render('public_reservations/settings',[
            'hours'=>PublicReservationService::getHours($restaurantId),
            'booking_url'=>rtrim(BASE_URL,'/').'/reserve/'.$token,
            'csrf_token'=>CSRF::getToken(),
            'title'=>'Online Reservations - ServeFlow POS'
        ],'app');
    }

    public function saveSettings(Request $request, array $params=[]): void
    {
        $restaurantId=$this->authorizeOwner();
        if(!CSRF::validate($request->csrfToken())) {
            Session::setFlash('danger','Your security token expired. Refresh the page and try again.');
            Response::redirect('/reservations/online-booking');
        }
        try {
            PublicReservationService::ensureSchema();
            PublicReservationService::saveHours($restaurantId,(array)$request->post('hours',[]));
            Session::setFlash('success','Online booking hours saved.');
        } catch(InvalidArgumentException $e) {
            Session::setFlash('warning',$e->getMessage());
        } catch(Throwable $e) {
            error_log('[Public Reservation Settings] '.$e->getMessage());
            Session::setFlash('danger','Unable to save online booking settings. Check the PHP error log.');
        }
        Response::redirect('/reservations/online-booking');
    }

    private function authorizeOwner(): int
    {
        Auth::requireAuth();
        if(!Auth::isPlatformAdmin() && !in_array(Auth::roleSlug(),['owner','manager'],true)) Response::forbidden('Only a restaurant owner or manager can manage online booking.');
        $id=(int)(TenantContext::getRestaurantId()??0);
        if($id<=0) Response::forbidden('Choose a restaurant workspace before managing online booking.');
        return $id;
    }
}
