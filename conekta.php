function make_payment_conekta($rahakkeitaId, $addressData, $referenceData, $referenceMoreInfo, $fields){
  $keys = arenapublica_suscripcion_get_api_key();
  \Conekta\Conekta::setApiKey( $keys["sk"] );
  \Conekta\Conekta::setApiVersion("2.0.0");

  $status = 200;

  try{
    try{
      $customer = \Conekta\Customer::create(
        array(
          "name" => $fields["nombre_tarjeta"],
          "email" => $fields["email"],
          //"phone" => '',
          "payment_sources" => array(
            array(
                "type" => "card",
                "token_id" => $rahakkeitaId
              )
            )//payment_sources
          )//customer
       );
    }
    catch (\Conekta\ProccessingError $error){ //Customer
       $message = $error->getMesage();
       $status = 400;
      } catch (\Conekta\ParameterValidationError $error){
          $message = $error->getMessage();
          $status = 401;
        } catch (\Conekta\Handler $error){
            $message = $error->getMessage();
            $status = 402;
          }

    try{

      $order = $customer-> createSubscription(array('plan' => _get_method_pay_plan_($fields["type-packet"])));
      $content = array(
         "id" => $order-> id,
         "card_number"=> '',
         "methods_logs" => array()
        );

        switch($order-> plan_id){
          case 'mensual-morning-shot-sp':
          case 'plan-3-min':
          case 'plan-5-min':
          case 'plan-10-min':
          case 'plan-3-min-fijo':
          case 'test-hora':
          case 'plan-semanal':
          case 'plan-semanal-mes':
          case 'plan-semanal-gratis':
           $content['methods_logs'] = array(
              'id' => $order-> id, //sub_2qFias1Kuywu9Ejo9
              'customer_id' => $order-> customer_id,//cus_2qFias1KuyobxGvhu
              'status' => $order-> status, // in_trial
              'object' => $order-> object, //subscription
              'created_at' => (int)$order-> created_at, // 1628712425
              'subscription_start' => $order-> subscription_start, //1628712425
              'plan_id' => $order-> plan_id, //mensual-morning-shot
              'card_id' => $order-> card_id, //src_2qFias1KuyobxGvhv
              'created_at_date' => format_fecha($order-> created_at, 1),

              'trial_end' => (int)$order-> trial_end, //1631304425
              'charge_id' => $order-> charge_id, //61142e8641de273c1cc708eb
              'billing_cycle_start' => (int) $order-> billing_cycle_start, //1628712572
              'billing_cycle_end' => (int)$order-> billing_cycle_end, //1660248572
              'last_billing_cycle_order_id' => $order-> last_billing_cycle_order_id, //ord_2qFicruxfHHijsFke
              'canceled_at' => (int)$order-> canceled_at,
              'paused_at' => (int)$order-> paused_at,

              'trial_end_bk' => (int)$order-> trial_end, //1631304425,
              'billing_cycle_end_bk' => (int)$order-> billing_cycle_end, //1660248572
              'status_bk' => $order-> status, // in_trial

            );
          break;
          case 'anual-morning-shot-sp':
          case 'plan-semanal-anual':
            $content['methods_logs'] = array(
              'id' => $order-> id, //sub_2qFicruxfHHijsFkb
              'customer_id' => $order-> customer_id, //cus_2qFicqzUDDYG9p6Qo
              'status' => $order-> status, //active
              'object' => $order-> object, //subscription
              'created_at' => (int) $order-> created_at, //1628712582
              'subscription_start' => (int) $order-> subscription_start, //1628712582
              'plan_id' => $order-> plan_id, //anual-morning-shot
              'card_id' => $order-> card_id, //src_2qFicqzUDDYG9p6Qp
              'created_at_date' => format_fecha($order-> created_at, 1),

              'trial_end' => (int)$order-> trial_end, //1631304425
              'charge_id' => $order-> charge_id, //61142e8641de273c1cc708eb
              'billing_cycle_start' => (int) $order-> billing_cycle_start, //1628712572
              'billing_cycle_end' => (int)$order-> billing_cycle_end, //1660248572
              'last_billing_cycle_order_id' => $order-> last_billing_cycle_order_id, //ord_2qFicruxfHHijsFke
              'canceled_at' => (int)$order-> canceled_at,
              'paused_at' => (int)$order-> paused_at,

              'trial_end_bk' => (int)$order-> trial_end, //1631304425,
              'billing_cycle_end_bk' => (int)$order-> billing_cycle_end, //1660248572
              'status_bk' => $order-> status, // in_trial            
            );
          break;
        }

      $orderSearch = _getData_contekta_api_search_api('/orders/'. $content['methods_logs']['last_billing_cycle_order_id'] );

      switch($orderSearch['payment_status']){
        case 'paid': 
            $status = 200;
            $messageStatus = "Pago hecho con éxito.";
            $message = "Pago hecho con éxito.";
          break;
        case 'declined': 
            $status = 602;
            $messageStatus = $orderSearch['charges']['data'][0]['failure_message'];
            $message = $orderSearch['charges']['data'][0]['failure_message'];
        break;
        default:
        break;
      }//

      if($status == 200){
        $customer = \Conekta\Customer::find($content['methods_logs']['customer_id']);

          if( (is_object($customer)) && (count($customer-> payment_sources)>0) ){
            foreach($customer-> payment_sources as $key => $items){
              if($items-> id === $content['methods_logs']['card_id']){
                $content['card_number'] = $items-> last4;
                break;
              }//
            }//foreach
          }//if
      }//status
    /* --- */
    } catch (\Conekta\ProcessingError $error){
          $message = $error->getMesage();
          $status = 403;
        } catch (\Conekta\ParameterValidationError $error){
          $message = $error->getMessage();
          $status = 405;
        } catch (\Conekta\Handler $error){
          $message = $error->getMessage();
          $status = 406;
        }

  }catch (\Conekta\Handler $error) {
    $message = $error->getMessage(). "|" . $error->getCode() . "|" . $error->getLine();
    $status = 407;
   }//

  return($status == 200) ? $content : array("error" => array("status"=> $status, "message" => $message ) );
}//make_payment_conekta
