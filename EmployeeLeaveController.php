<?php

namespace App\Http\Controllers\Organization\hrm;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
USE App\Model\Organization\Leave as EMP_LEV;
USE App\Model\Organization\Category as cat;
USE App\Model\Organization\Employee as EMP;
USE App\Model\Organization\CategoryMeta as catMeta;
use Auth;
use Carbon\Carbon;
use Session;

class EmployeeLeaveController extends Controller
{

	public function leave_listing(){
		$leave_count_by_cat =$leave_rule =$leavesData = $error =null;
		if(in_array(1, role_id())){
			$error = "You can not view leave.";
		}else{
				$catMetas = catMeta::whereIn('key',['include_designation','user_include','user_exclude'])->get();
				dd($catMetas->groupBy('key')->toArray());
			$user = user_info()->toArray();	
			$designation_id =  get_current_user_meta('designation');
			dump($user['id']);
			$leave_rule = cat::with('leave_meta')->where(['type'=>'leave', 'status'=>1])->get();
			dump($leave_rule->toArray());

			$emp_id = get_current_user_meta('employee_id');
			$leavesData = EMP_LEV::where(['employee_id'=>$emp_id])->get();
			$leave_count_by_cat = $leavesData->where('status',1)->groupBy('leave_category_id');
			// dump($data);
			// // $collect = collect($leavesData->toArray());
			// // dump($collect->where('status',1));
			// $leave_count_by_cat = $data->groupBy('leave_category_id');
		}
		return view('organization.profile.leaves',['data'=>$leavesData, 'leave_rule'=>$leave_rule , 'leave_count_by_cat'=>$leave_count_by_cat,'error'=>$error]);
	}

	Public function store(Request $request, $id=null)
	{ 
		$user = user_info()->toArray();	

		echo $emp_id = get_current_user_meta('employee_id');	
		if($request->isMethod('post')){

			// $request['reason_of_leave']  = $request['accleasec1f1'];
			$current = Carbon::now();
			$from = Carbon::parse($request->from);
			
			$before = $from->diffInDays($current);
			$to = Carbon::parse($request->to);

			$request['from'] 	=	$from->toDateString();
			$request['to'] 		=   $to->toDateString();

		$data =	EMP_LEV::where(function($query)use($request){
				$query->whereBetween('from', [$request['from'], $request['to'] ])->orWhereBetween('to',[$request['from'], $request['to']]);
			})->where('employee_id',$emp_id);

		if($data->exists()){
			$error['already_taken_leave_between_from_and_to_date'] = 'Already apply leave between from and to date Correct the dates.';

		}
	

			if($to->month < $from->month )
			{
				$error['from_greater_than_to'] = 'from date must be less than to date.';
				Session::flash('error',$error);
				return redirect()->route('account.leaves');
			}


			$request['total_days'] = $from->diffInDays($to) + 1; 
			
			$rules = catMeta::where('category_id', $request['leave_category_id']);
			

			if($rules->exists())
			{	
				$rule_check = json_decode($rules->get()->keyBy('key'),true);
			if(!empty($rule_check['include_designation']['value']))
				{
					$include_designation = array_map('intval',json_decode($rule_check['include_designation']['value'],true));
					if(!in_array($user['employee_rel']['designation'], $include_designation))
					{
						$error['include_designation'] = "Designation not Includes"; 
					}
				}
/*Designation Include Check*/
				elseif(!empty($rule_check['exclude_designation']['value'])){
					$exclude_designation = array_map('intval',json_decode($rule_check['exclude_designation']['value'],true));
					if(in_array($user['employee_rel']['designation'], $exclude_designation))
					{
						$error['exclude_designation'] = "Exclude Designation"; 
					}
				}
//user Include Check 				
				if(!empty($rule_check['user_include']['value']))
				{
					$include_designation = array_map('intval',json_decode($rule_check['user_include']['value'],true));
					if(!in_array($user['id'], $include_designation))
					{
						$error['user_include'] = "User not Includes"; 
					}
				}
/*user exclude Check*/
				elseif(!empty($rule_check['user_exclude']['value'])){
					$user_exclude = array_map('intval',json_decode($rule_check['user_exclude']['value'],true));
					if(in_array($user['id'], $exclude_designation))
					{
						$error['user_exclude'] = "Exclude User"; 
					}
				}

				//Role Include Check 				
				// if(!empty($rule_check['role_include']['value']))
				// {
				// 	$role_include = array_map('intval',json_decode($rule_check['role_include']['value'],true));
				// 	$roleIdExistingVal = array_intersect($role_include,role_id());
				// 	if(empty($roleIdExistingVal))
				// 	{
				// 		$error['role_include'] = "Role not Includes"; 
				// 	}
				// }
/*Role Include Check*/
				// elseif(!empty($rule_check['roles_exclude']['value'])){
				// 	$roles_exclude = array_map('intval',json_decode($rule_check['roles_exclude']['value'],true));
				// 	$roleIdExcludeExistingVal = array_intersect($roles_exclude,role_id());
				// 	if(empty($roleIdExcludeExistingVal))
				// 	{
				// 		$error['roles_exclude'] = "Exclude Role"; 
				// 	}					
				// }


				if($request['total_days'] > $rule_check['number_of_day']['value'])
				{
					$error['exceed_number_of_day'] = "You can only take leave  ".$rule_check['number_of_day']['value']; 
				}

				if($rule_check['valid_for']['value'] == "monthly")
				{
						$leaveFrm = EMP_LEV::where(['employee_id'=>$emp_id, 'leave_category_id'=>$request['leave_category_id']])->whereMonth('from',array($from->month))->get()->keyBy('id');

							$leaveTo = EMP_LEV::where(['employee_id'=>$emp_id, 'leave_category_id'=>$request['leave_category_id']])->whereMonth('to',array($from->month))->get()->keyBy('id');
							$leaveData = $leaveFrm->merge($leaveTo);//->toArray();
					if($from->month != $to->month)
					{
						$leaveToFormReq = EMP_LEV::where(['employee_id'=>$emp_id, 'leave_category_id'=>$request['leave_category_id']])->whereMonth('from',array($to->month))->get()->keyBy('id');

						$leaveToReq = EMP_LEV::where(['employee_id'=>$emp_id, 'leave_category_id'=>$request['leave_category_id']])->whereMonth('to',array($to->month))->get()->keyBy('id');
							$data = $leaveToFormReq->merge($leaveToReq);
							$leaveData = $data->merge($leaveData);
					}				

					foreach($leaveData->toArray() as $key => $val){
						$fromMo = Carbon::parse($val['from']);
						$toMo = Carbon::parse($val['to']);
						if($fromMo->month != $toMo->month){
							if($from->month == $fromMo->month ){
								$totalMoDay = $from->daysInMonth;
								$total_days[$fromMo->month][] = $totalMoDay - $fromMo->day; 
							}
							elseif($from->month == $toMo->month ){
								$total_days[$toMo->month][] = $toMo->day; 
							}
						}
						else{
							$total_days[$toMo->month][] = $val['total_days'];
						}
					 }
					 // dump($from->month,  $to->month);
					if(!empty($total_days))
					 {
					 	if($from->month == $to->month)
						{
								$takenLeave = collect($total_days[$from->month])->sum();
								$sumAll = $request['total_days'] + $takenLeave;
								if($sumAll >$rule_check['number_of_day']['value'])
								{
									$error['exceed_number_of_day'] = "You already taken leave  ".$takenLeave."&&  Now your applying leave for ".$request['total_days'].' day'; 
								}
						}
						elseif($from->month != $to->month){
							$fromTakenLeave =null;
							if(!empty($total_days[$from->month])){
								$fromTakenLeave = collect($total_days[$from->month])->sum();
							}
							
							if($from->day == $from->daysInMonth)
							{
								$totalFrm = $fromTakenLeave +1;   
								if($totalFrm > $rule_check['number_of_day']['value'])
								{
									$error['exceed_number_of_day'] = "you exceed leave limit in month ".$from->month;
								}
							}else{

									$totalFrm = $from->daysInMonth - $from->day;
									$totalSumFrom = $fromTakenLeave + $totalFrm;
									if($totalSumFrom > $rule_check['number_of_day']['value'])
									{
										 $error['exceed_number_of_day'] = "you exceed leave limit in month ".$from->month;
									}
								}
							$toTakenLeave = collect($total_days[$to->month])->sum();
							$totalTo = $to->day + $toTakenLeave;

							if($totalTo > $rule_check['number_of_day']['value'])
									{
										 $error['exceed_number_of_day'] = "you exceed leave limit in month ".$to->month;
									}
						}
				}
					// echo "to sum";
					 //dump( @$total_days);

					// $leave_sumdays = $leave->sum('total_days');
					// $total_sum = $leave_sumdays + $request['total_days'];
					// if($total_sum >= $rule_check['number_of_day']['value'])
					// {
					// 	$error['exceed_number_of_day'] = "You already taken leave include current  ".$total_sum; 
					// }
					
				}
				elseif($rule_check['valid_for']['value'] == "yearly")
				{
					$leave_sumdays = EMP_LEV::where(['employee_id'=>$emp_id, 'leave_category_id'=>$request['leave_category_id']])->whereYear('from',array($from->year))->sum('total_days');
						$total_sum = $leave_sumdays + $request['total_days'];
						if($total_sum >= $rule_check['number_of_day']['value'])
						{
							$error['exceed_number_of_day'] = "You already taken leave  ".$leave_sumdays; 
						}
				}

				if($rule_check['apply_before']['value'] > $before)
				{
					$error['apply_before'] = "Apply leave After ".$rule_check['apply_before']['value']; 
				}	
				// dump(@$error);
			//dd($rule_check);


			}
			if(empty($error)) {
				
				$leave = new EMP_LEV();	
				$request['employee_id'] = $emp_id;  
				$leave->fill($request->all());
				$leave->save();
				save_activity('apply_leave');
				Session::flash('sucessful', 'Successfully Apply Leave ');

			}else{
				Session::flash('error', $error);
					//dd($error);
			}
		 }
 	return redirect()->route('account.leaves');
	}
    
}

// else if($request->isMethod('patch')){
// 			$leave_id = $request['leave_id'];
// 			unset( $request['leave_id'] , $request['_method'] , $request['_token']);
// 			EMP_LEV::where('id', $leave_id)->update($request->all());
// 		}
// 		elseif($request->isMethod('DELETE')){
// 			$data = EMP_LEV::find($request['delete_id']);
// 			$data->delete();
// 		}