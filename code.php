<?php
class Model_Factory  extends \Model
{
	public function factory($type, $postInfo)
	{
		switch ($type) {
			case "property":
				$result = $this->_propertyInfo($postInfo);
				break;
			case "ticket":
				$result = $this->_ticketInfo($postInfo);
				break;
			case "package":
				$result = $this->_packageInfo($postInfo);
				break;
			case "rate":
				$result = $this->_rateInfo($postInfo);
				break;
		}

		return $result;
	}

	protected function _propertyInfo($postInfo)
	{
		$nights = array();
		$rooms_total = array(1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8);
		$propertyInfo = array('room' => array(), 
							  'room_id' => $postInfo['room_id'],
							  'rooms' => array(),
							  'rooms_total' => $rooms_total,
							  'nights' => $nights);

		$propertyObject = $this->_getProperty($postInfo['property_id']);
		$roomObject = $this->_getRoom($postInfo['room_id']);

		if ($roomObject) {
			$propertyInfo['room']['name'] = $roomObject->name;
			$propertyInfo['room']['number'] = $roomObject->number;
		}

		foreach ($propertyObject->rooms as $room) {
			$propertyInfo['rooms'][$room->id] = $room->name;
			$minNights = $room->minimum_nights;
			$maxNights = $room->maximum_nights;
		}

		for ($i = $minNights; $i <= $maxNights; $i++) {
			$nights[$i] = $i;
		}

		if (empty($nights)) {
			$nights = array(1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8);
		}

		$propertyInfo['nights'] = $nights;

		$propertyInfo['id'] = $propertyObject->id;
		$propertyInfo['name'] = $propertyObject->name;
		$propertyInfo['amenities'] = $propertyObject->amenities;

		$propertyInfo['qualifications'] = $propertyObject->qualifications;
		$propertyInfo['cancellations'] = $propertyObject->cancellations;
		$propertyInfo['billing'] = $propertyObject->billing;

		return $propertyInfo;
	}

	protected function _ticketInfo($postInfo)
	{
		$nights = array(1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8);
		$ticketInfo = array('rate' => 0, 'nights' => $nights);

		$ticketsObject = $this->_getTickets($postInfo['ticket_id']);

		foreach ($ticketsObject as $ticket) {
			$ticketInfo['name'] = $ticket->name;
			$ticketInfo['number'] = array();
			foreach ($ticket->offers as $offer) {
				$ticketInfo['number'][$offer->number] = $offer->number;

				if ($offer->number === $postInfo['ticket_number']) {
					$ticketInfo['rate'] = $offer->rate_ticket;
					$ticketInfo['retail'] = $ticket->retail_rate * $postInfo['ticket_number'];
				}
			}
		}

		$ticketInfo['id'] = $postInfo['ticket_id'];
		$ticketInfo['ticket_number'] = $postInfo['ticket_number'];
		$ticketInfo['property_name'] = $postInfo['property_name'];

		return $ticketInfo;
	}

	protected function _packageInfo($postInfo)
	{
		$packageInfo = array();
		$roomInfo = array();
		$nights = array();

		$packageInfo['id'] = $postInfo['package_id'];
		$packagesObject = $this->_getPackages($postInfo['package_id']);

		foreach ($packagesObject as $package) {
			$propertyObject = $this->_getProperty($package->property_id);
			$roomObject = $this->_getRoom($postInfo['room_id']);

			$rateTickets = $package->offers->rate + $package->addition;
			$retailTickets = $package->offers->tickets->retail_rate * $package->offers->number;
			$packageInfo['property_name'] = $propertyObject->name;
			$packageInfo['property_id'] = $propertyObject->id;

			if ($roomObject) {
				$packageInfo['room']['name'] = $roomObject->name;
				$packageInfo['room']['number'] = $roomObject->number;
			}

			$packageInfo['rate'] = $rateTickets;
			$packageInfo['ticket_id'] = $package->offers->tickets->id;
			$packageInfo['name'] = $package->offers->tickets->name;
			$packageInfo['number'] = $package->offers->number;
			$packageInfo['rateTickets'] = $rateTickets;
			$packageInfo['retailTickets'] = $retailTickets;
		}

		foreach ($propertyObject->rooms as $room) {
			$roomInfo[$room->id] = $room->name;
			$minNights = $room->minimum_nights;
			$maxNights = $room->maximum_nights;
		}
		$packageInfo['rooms'] = $roomInfo;

		for ($i = $minNights; $i <= $maxNights; $i++) {
			$nights[$i] = $i;
		}
		$packageInfo['nights'] = $nights;

		$packageInfo['qualifications'] = $propertyObject->qualifications;
		$packageInfo['cancellations'] = $propertyObject->cancellations;
		$packageInfo['billing'] = $propertyObject->billing;

		return $packageInfo;
	}

	protected function _rateInfo($postInfo)
	{
		$type = (isset($postInfo['discount'])) ? 'discount_rate' : 'rate';
		$rateInfo = array('noRates' => false, 'rate' => 0, 'retail' => 0);
		$roomObject = $this->_getRoom($postInfo['room_id']);

		$dateArrivalDigits = $this->_formatDate($postInfo['date_arrival']);
		$totalNights = $postInfo['nights'] - 1;
		$dateArrivalTimestamp = strtotime($dateArrivalDigits);
		$dateArrivalFormat = Date::forge($dateArrivalTimestamp)->format("%Y-%m-%d");
		$dateDepartureTimestamp = strtotime($dateArrivalFormat . " +$totalNights day");
		$dateDepartureFormat = Date::forge($dateDepartureTimestamp)->format("%Y-%m-%d");

		for ($i = 0; $i <= $totalNights; $i++ ) {
			$rateInfo['retail'] += $roomObject->retail_rate;
		}

		$sql = 'SELECT * FROM rates WHERE room_id = '.Input::post('room_id').' AND date BETWEEN \''.$dateArrivalFormat.'\' AND \'' . $dateDepartureFormat . '\'';
		$rates = $this->_getRates($sql);
		$rateInfo['count'] =  count($rates);

		foreach ($rates as $rate) {
			if ($rate[$type] === '0.00') {
				$rateInfo['noRates'] = true;
			}
			
			$rateInfo['rate'] += $rate[$type];
		}

		if (isset($postInfo['discount'])) {
			$rateInfo['rate'] = $rateInfo['rate'] * $postInfo['rooms'];
			$rateInfo['retail'] = $rateInfo['retail'] * $postInfo['rooms'];
			return $rateInfo;
		}
		
		for ($i = $roomObject->minimum_nights; $i < $postInfo['nights']; $i++) {
			$rateInfo['rate'] += $roomObject->additional_nights;
		}

		return $rateInfo;
	}

	protected function _formatDate($date) {
		$formattedDate = preg_replace('/^[a-zA-Z]+/', '', $date);
		$formattedDate = trim($formattedDate);

		return $formattedDate;
	}

	protected function _getTickets($id) 
	{
		$tickets = Model_Ticket::find('all', array('where' => array(array('id', '=', $id)), 
												   'related' => array('offers')));

		return $tickets;
	}

	protected function _getProperty($id) 
	{
		$property = Model_Property::find($id, array('related' => array('rooms')));

		return $property;
	}

	protected function _getRoom($id) 
	{
		$room = Model_Room::find_by_id($id);

		return $room;
	}


	protected function _getPackages($id) 
	{
		$packages = Model_Package::find('all', array('where' => array(array('id', '=', $id)), 
													 'related' => array('offers', 'offers.tickets')));

		return $packages;
	}

	protected function _getRates($sql) 
	{
		$rates = DB::query($sql)->execute();

		return $rates;
	}
}
