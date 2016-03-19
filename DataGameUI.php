<?php

include_once 'DialogConditionFactory.php';
include_once 'RPGNPC.php';
include_once 'RPGTime.php';
include_once 'RPGCombat.php';
include_once 'RPGFloor.php';
include_once 'constants.php';

class DataGameUI{
	
	public function DataGameUI(){
		$this->handleEvents();
		$this->handleTicks();
	}
	
	public function handleEvents(){
		global $arrStateNames;
		global $arrStateValues;

		// town events are accessed using the URL ($_GET)
		$this->handleTownEvents();
		$objEvent = new RPGEvent($_SESSION['objRPGCharacter']->getEventID());
		$objXML = new RPGXMLReader($objEvent->getXML());
		
		$blnEndOfEvent = $_SESSION['objRPGCharacter']->checkEndOfEvent();
			
		// if initiated combat from an event
		if($_SESSION['objRPGCharacter']->getCombat()[0] != 0 && !isset($_SESSION['objCombat'])){
			$objEnemy = new RPGNPC($_SESSION['objRPGCharacter']->getCombat()[0]);
			$_SESSION['objCombat'] = new RPGCombat($_SESSION['objRPGCharacter'], $objEnemy, $_SESSION['objRPGCharacter']->getCombat()[1]);
			$_SESSION['objCombat']->initiateCombat();
			$_SESSION['objRPGCharacter']->setStateID($arrStateValues['Combat']);
		}

		// enable/disable menus and such according to state we're in
		switch($arrStateNames[$_SESSION['objRPGCharacter']->getStateID()]){
			case "Tutorial":
				$_SESSION['objUISettings']->setDisableTraversal(true);
				$_SESSION['objUISettings']->setDisableInv(false);
				$_SESSION['objUISettings']->setDisableStats(true);
				$_SESSION['objUISettings']->setEventFrame('Event');
				$_SESSION['objUISettings']->setCommandsFrame('Event');
				$_SESSION['objUISettings']->setNavigationFrame('Compass');
				break;
			case "Event":
				if((($objXML->getEventType() == "Forced Combat" || $objXML->getEventType() == "Forced Event") && !$blnEndOfEvent) || ($_SESSION['objRPGCharacter']->getEventNodeID() != 0 && !$blnEndOfEvent)){
					$_SESSION['objUISettings']->setDisableTraversal(true);
					$_SESSION['objUISettings']->setDisableInv(true);
					$_SESSION['objUISettings']->setDisableStats(true);
				}
				else{
					$_SESSION['objUISettings']->setDisableTraversal(false);
					$_SESSION['objUISettings']->setDisableInv(false);
					$_SESSION['objUISettings']->setDisableStats(false);
				}
				$_SESSION['objUISettings']->setEventFrame('Event');
				if($blnEndOfEvent && $_SESSION['objRPGCharacter']->getTownID() == 1){
					$_SESSION['objUISettings']->setCommandsFrame('Return');	
					$_SESSION['objUISettings']->setNavigationFrame('Menu');
					$_SESSION['objUISettings']->setDisableTraversal(true);
				}
				else if(!$blnEndOfEvent && $_SESSION['objRPGCharacter']->getTownID() == 1){
					$_SESSION['objUISettings']->setCommandsFrame('Event');	
					$_SESSION['objUISettings']->setNavigationFrame('Compass');
					$_SESSION['objUISettings']->setDisableTraversal(true);
				}
				else{
					$_SESSION['objUISettings']->setCommandsFrame('Event');	
					$_SESSION['objUISettings']->setNavigationFrame('Compass');
				}
				break;
			case "Combat":
				$_SESSION['objUISettings']->setDisableTraversal(true);
				$_SESSION['objUISettings']->setDisableInv(true);
				$_SESSION['objUISettings']->setDisableStats(true);
				$_SESSION['objUISettings']->setEventFrame('Combat');
				$_SESSION['objUISettings']->setCommandsFrame('Combat');
				$_SESSION['objUISettings']->setNavigationFrame('Compass');
				break;
			case "Field":
				$_SESSION['objUISettings']->setDisableTraversal(false);
				$_SESSION['objUISettings']->setDisableInv(false);
				$_SESSION['objUISettings']->setDisableStats(false);
				$_SESSION['objUISettings']->setEventFrame('Event');
				$_SESSION['objUISettings']->setCommandsFrame('Event');
				$_SESSION['objUISettings']->setNavigationFrame('Compass');
				break;
			case "Town":
				$_SESSION['objUISettings']->setDisableTraversal(false);
				$_SESSION['objUISettings']->setDisableInv(false);
				$_SESSION['objUISettings']->setDisableStats(false);
				$_SESSION['objUISettings']->setEventFrame('Town');
				$_SESSION['objUISettings']->setCommandsFrame('Town');
				$_SESSION['objUISettings']->setNavigationFrame('Menu');
				break;
			case "Stats":
				$_SESSION['objUISettings']->setDisableTraversal(true);
				$_SESSION['objUISettings']->setDisableInv(true);
				$_SESSION['objUISettings']->setDisableStats(false);
				$_SESSION['objUISettings']->setEventFrame('StatGain');
				$_SESSION['objUISettings']->setCommandsFrame('Return');
				$_SESSION['objUISettings']->setNavigationFrame('Compass');
				break;
			case "Shop":
				$_SESSION['objUISettings']->setDisableTraversal(true);
				$_SESSION['objUISettings']->setDisableInv(false);
				$_SESSION['objUISettings']->setDisableStats(true);
				$_SESSION['objUISettings']->setEventFrame('Shop');
				$_SESSION['objUISettings']->setCommandsFrame('Return');
				$_SESSION['objUISettings']->setNavigationFrame('Compass');
				break;
			default:
				$_SESSION['objRPGCharacter']->setStateID($arrStateValues['Tutorial']);
				$_SESSION['objUISettings']->setDisableTraversal(true);
				$_SESSION['objUISettings']->setDisableInv(false);
				$_SESSION['objUISettings']->setDisableStats(true);
				$_SESSION['objUISettings']->setEventFrame('Event');
				$_SESSION['objUISettings']->setCommandsFrame('Event');
				$_SESSION['objUISettings']->setNavigationFrame('Compass');
				break;
		}	

	}
	
	public function handleTownEvents(){
		global $arrStateValues;
		
		if(isset($_GET['EnterFloorID'])){
			if($_SESSION['objRPGCharacter']->getFloor() >= $_GET['EnterFloorID'] && $_SESSION['objRPGCharacter']->getTownID() == 1){
				$_SESSION['objRPGCharacter']->enterFloor($_GET['EnterFloorID']);
				return;
			}
		}
		
		if($_SESSION['objRPGCharacter']->getTownID() == 1){
			// todo: verify with eventlinkxr or shoplinkxr that they are allowed to view the event/shop
			
			if(isset($_GET['EventID'])){
				$objEvent = new RPGEvent($_GET['EventID']);
				$objXML = new RPGXMLReader($objEvent->getXML());
				
				$blnConditionsPassed = false;
				foreach($objXML->getPrecondition() as $key => $value){
					if (!DialogConditionFactory::evaluateCondition($value)){
						$blnConditionsPassed = false;
					}
					else{
						$blnConditionsPassed = true;
					}
				}
				
				if($blnConditionsPassed){
					$_SESSION['objRPGCharacter']->setEventID($objEvent->getEventID());
					$_SESSION['objRPGCharacter']->setEventNodeID(0);
					$_SESSION['objRPGCharacter']->setStateID($arrStateValues['Event']);
				}
			}
			else if(isset($_GET['ShopID'])){
				$_SESSION['objRPGCharacter']->setStateID($arrStateValues['Shop']);
			}
			
			if(isset($_GET['LocationID'])){
				$_SESSION['objRPGCharacter']->setLocationID($_GET['LocationID']);
			}
			
		}
	}
	
	public function handleTicks(){
		$_SESSION['objRPGCharacter']->setTime(RPGTime::addTickToTime($_SESSION['objRPGCharacter']->getTime()));
		$_SESSION['objRPGCharacter']->digestItems();
		$_SESSION['objRPGCharacter']->tickStatusEffects();
		
		// immobility debuff
		if($_SESSION['objRPGCharacter']->getImmobilityFactor() > 0.04 && !isset($_SESSION['objUISettings']->getOverrides()[4])){
			$_SESSION['objRPGCharacter']->addToStatusEffects('Burdened by Weight', 4);
		}
		else if($_SESSION['objRPGCharacter']->getImmobilityFactor() < 0.04 && isset($_SESSION['objUISettings']->getOverrides()[4])){
			$_SESSION['objRPGCharacter']->removeFromStatusEffects('Burdened by Weight');		
		}
		
		$_SESSION['objRPGCharacter']->save();
	}	
}

?>