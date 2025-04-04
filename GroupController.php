<?php

namespace App\Http\Controllers;

use App\Mail\GroupInvitationMail;
use App\Models\Expense;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Itinerary;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class GroupController extends Controller
{

    public function getDirectPayments($id)
    {
        $user = Auth::user();
        
        $isAdmin = $user->groups()->where('group_id', $id)->wherePivot('role', 'admin')->exists();
        
        if ($isAdmin) {
            $payments = Payment::whereHas('expense', function ($query) use ($id) {
                $query->where('group_id', $id);
            })->with(['payer:id,names,last_name', 'payee:id,names,last_name'])
              ->get()
              ->map(function ($payment) {
                  $payment->payer_name = $payment->payer->names . ' ' . $payment->payer->last_name;
                  $payment->payee_name = $payment->payee->names . ' ' . $payment->payee->last_name;
                  unset($payment->payer, $payment->payee);
                  return $payment;
              });
        } else {
            $payments = Payment::whereHas('expense', function ($query) use ($id) {
                $query->where('group_id', $id);
            })->where('payee_id', $user->id)
              ->with(['payer:id,names,last_name', 'payee:id,names,last_name'])
              ->get()
              ->map(function ($payment) {
                  $payment->payer_name = $payment->payer->names . ' ' . $payment->payer->last_name;
                  $payment->payee_name = 'Tú';
                  unset($payment->payer, $payment->payee);
                  return $payment;
              });
        }
        
        return response()->json(["payments" => $payments]);
    }

    public function getInviteLink($id)
    {
        $group = Group::find($id);

        if (!$group) {
            return response()->json(['message' => 'Grupo no encontrado'], 404);
        }

        return response()->json([
            'message' => 'Enlace de invitación generado',
            'invite_link' => '/invite/' . $group->invite_token
        ]);
    }

    public function inviteByEmail(Request $request, $id)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $group = Group::find($id);

        if (!$group) {
            return response()->json(['message' => 'Grupo no encontrado'], 404);
        }

        Mail::to($request->email)->send(new GroupInvitationMail($group));

        return response()->json(['message' => 'Invitación enviada con éxito']);
    }

    public function joinGroup($token)
    {
        $group = Group::where('invite_token', $token)->first();
    
        if (!$group) {
            return response()->json(['message' => 'Enlace inválido o expirado'], 404);
        }
    
        $user = Auth::guard('sanctum')->user();
    
        if (!$user) {
            return response()->json([
                'message' => 'Debes iniciar sesión o registrarte antes de unirte al grupo',
                'login_url' => config('app.frontend_url'). '/login?invite_token=' . $token,
                'register_url' => config('app.frontend_url'). '/register?invite_token=' . $token,
                'requires_auth' => true
            ], 401);
        }

        if ($group->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Ya eres miembro de este grupo'], 400);
        }
    
        $group->members()->attach($user->id, ['status' => 'active']);
    
        return response()->json(['message' => 'Te has unido al grupo exitosamente']);
    } 
}