<html>
	<head>
		<title>Welcome to Flight Booking</title>
	</head>
	<body>
<?php
include("db.php");
include("auth.php");
require ('config.php');	
include("mongo.php");

if(isset($_POST ['stripeToken'])){
	
	$from="'".$_SESSION['from']."'";
	$to="'".$_SESSION['to']."'";
	$date1="'".$_SESSION['date1']."'";
	$date2="'".$_SESSION['date2']."'";
	$bc ="'".$_SESSION["bc"]."'";
	$jid ="'".$_SESSION["jid"]."'";
	$rid ="'".$_SESSION["rid"]."'";
	$pn ="'".$_SESSION["pn"]."'";
	$pn1 = $_SESSION["pn"];
	
	
	$qa = "call dec_seat_flight(".$jid.",".$pn.",".$bc.")";
	$resulta = $con->query($qa);
	
	$db = $client->flight_booking->flight;	/* Mongodb */
	$document = $db->findOne( array('date'=>$_SESSION['date1'], 'toFlight.id'=> $_SESSION['jplanid'] ) );
	if($_SESSION["bc"]==0)
	
	{	
		$deleteResult = $db->deleteOne(['date'=>$_SESSION['date1'], 'toFlight.id'=> $_SESSION['jplanid']]);
		$document['toFlight'][0]['available_EC_Seat']=$document['toFlight'][0]['available_EC_Seat']-$pn1;
		$create = $db->insertOne($document);
	}
	else
	
	{ 	$deleteResult = $db->deleteOne(['date'=>$_SESSION['date1'], 'toFlight.id'=> $_SESSION['jplanid']]);
		$document['toFlight'][0]['available_BC_Seat']=$document['toFlight'][0]['available_BC_Seat']-$pn1;
		$create = $db->insertOne($document);
	}

	if($_SESSION["rt"]==1){
		$qb = "call dec_seat_flight(".$rid.",".$pn.",".$bc.")";
		$resultb = $con->query($qb);
		
		$db = $client->flight_booking->flight;	/* Mongodb */
		$document = $db->findOne( array('date'=>$_SESSION['date2'], 'toFlight.id'=> $_SESSION['rplanid'] ) );
		if($_SESSION["bc"]==0)
		
		{	$deleteResult = $db->deleteOne(['date'=>$_SESSION['date2'], 'toFlight.id'=> $_SESSION['rplanid']]);
			$document['toFlight'][0]['available_EC_Seat']=$document['toFlight'][0]['available_EC_Seat']-$pn1;
			$create = $db->insertOne($document);
		}
		else
		
		{ 	$deleteResult = $db->deleteOne(['date'=>$_SESSION['date2'], 'toFlight.id'=> $_SESSION['rplanid']]);
			$document['toFlight'][0]['available_BC_Seat']=$document['toFlight'][0]['available_BC_Seat']-$pn1;
			$create = $db->insertOne($document);
		}	
	}
	
	
	$token = $_POST ['stripeToken'];
	$data = \Stripe\Charge::create(array(
		'amount' => $_SESSION["atp"]*100,
		'description' =>"Online air ticket booking",
		'currency' => "dkk",
		'source'=> $token,
	));

	$datab = $data->source;
	
	$paymentid="'".$data->id."'";
	$brand="'".$datab->brand."'";
	$last4="'".$datab->last4."'";
	$paymentid1=$data->id;
	$brand1=$datab->brand;
	$last41=$datab->last4;
	$userid="'".$_SESSION["userid"]."'";
	$atp ="'".$_SESSION["atp"]."'";
	$jprice ="'".$_SESSION["jprice"]."'";
	$rprice ="'".$_SESSION["rprice"]."'";
	
	$sqlbking= "call insert_booking(".$userid.",".$pn.",".$date1.",".$date2.",".$bc.",".$paymentid.",".$brand.",".$last4.",".$atp.")";	
	$resultbk = $con->query($sqlbking);
	$row = $resultbk->fetch_assoc();
	echo "Transfer Successful.<br>Thank you for purchase.<br>Your booking ID is:".$row["BookingID"]."<br> Your tranfer details <br>";
	echo "Transfer ID: ".$paymentid."<br>Card: ".$brand."<br>Last 4 degite of Your card: ".$last4."Total amount Charged: ".$atp."dkk.";
	$bookingID = $row["BookingID"];
	//$con->commit();
	$con->close();
	
	$db = $client->flight_booking->booking;/* Mongodb */
	$insertOneResult = $db->insertOne([
	'id' =>$bookingID,
	'userID' => $_SESSION["userid"],
	'NumberPassenger' => $_SESSION["pn"],
	'Date' => $_SESSION["date1"],
	'ReturnDate' => $_SESSION["date2"],
	'BusinessClass' => $_SESSION["bc"],
	'paymentID' => $paymentid1,
	'paymentType' => $brand1,
	'cardNumber' => $last41,
	'totalPrice' => $_SESSION["atp"],
	]);
	
	
	
	//$conn->begin_transaction();				  
	for($y=0;$y<=$_SESSION["pn"]-1;$y++){
		$conn = mysqli_connect("localhost","root","rony2204","flight_booking");
		if (mysqli_connect_errno())
		{
			echo "Failed to connect to MySQL: " . mysqli_connect_error();
		}
		$sqlpb = "INSERT INTO passengerbooking (BookingID,passengerName)
		VALUES ('".$row["BookingID"]."',".$_SESSION["passanger"][$y].")";
		if (mysqli_query($conn, $sqlpb)) {
			$last_id = mysqli_insert_id($conn);
			
			$db = $client->flight_booking->passengerbooking;/* Mongodb */
			$insertOneResult = $db->insertOne([
			'id' =>$last_id,
			'BookingID' => $row["BookingID"],
			'passengerName' => $_SESSION["passanger"][$y],
			]);
			$conn->close();
			
			
			$con1 = mysqli_connect("localhost","root","rony2204","flight_booking");
			if (mysqli_connect_errno())
			{
				echo "Failed to connect to MySQL: " . mysqli_connect_error();
			}
			$sqlitj ="call insert_ticket(".$jid.",".$last_id.",".$jprice.",".$bc.")";
			$resultitj = $con1->query($sqlitj);
			$last_idd = mysqli_insert_id($con1);
			$con1->close();
			$db = $client->flight_booking->flight; /* Mongodb */
			$document = $db->findOne( array('date'=>$_SESSION["date1"], 'toFlight.id'=> $_SESSION["jplanid"] ) );
			$db = $client->flight_booking->ticket;
			$insertOneResult = $db->insertOne([
				'id' =>$last_idd,
				'BookingID' => $row["BookingID"],
				'passengerName' => $_SESSION["passanger"][$y],
				'AirlinesName' => $document['toFlight'][0]['AirlinesName'],
				'AricraftName' => $document['toFlight'][0]['AricraftName'],
				'Flight_Num' => $_SESSION["jplanid"],
				'Date' => $_SESSION["date1"],
				'Departure_From' => $_SESSION["from"],
				'Arrival_To' => $_SESSION["to"],
				'DepartureTime' => $document['toFlight'][0]['DepartureTime'],
				'ArrivalTime' => $document['toFlight'][0]['ArrivalTime'],
				'Class' => $_SESSION["bc"],
				'price' => $_SESSION["jprice"],
			]);
			if($_SESSION["rt"]==1){	
				$con2 = mysqli_connect("localhost","root","rony2204","flight_booking");
				if (mysqli_connect_errno())
				{
					echo "Failed to connect to MySQL: " . mysqli_connect_error();
				}
				$sqlitr ="call insert_ticket(".$rid.",".$last_id.",".$rprice.",".$bc.")";
				$resultitr = $con2->query($sqlitr);
				$last_idr = mysqli_insert_id($con2);
				$con2->close();
				$db = $client->flight_booking->flight; /* Mongodb */
				$document = $db->findOne( array('date'=>$_SESSION["date2"], 'toFlight.id'=> $_SESSION["rplanid"] ) );
				$db = $client->flight_booking->ticket;
				$insertOneResult = $db->insertOne([
				'id' =>$last_idr,
				'BookingID' => $row["BookingID"],
				'passengerName' => $_SESSION["passanger"][$y],
				'AirlinesName' => $document['toFlight'][0]['AirlinesName'],
				'AricraftName' => $document['toFlight'][0]['AricraftName'],
				'Flight_Num' => $_SESSION["rplanid"],
				'Date' => $_SESSION["date2"],
				'Departure_From' => $_SESSION["to"],
				'Arrival_To' => $_SESSION["from"],
				'DepartureTime' => $document['toFlight'][0]['DepartureTime'],
				'ArrivalTime' => $document['toFlight'][0]['ArrivalTime'],
				'Class' => $_SESSION["bc"],
				'price' => $_SESSION["rprice"],
				]);
			}
		} else {
			echo "Error: " . $sqlpb . "<br>" . mysqli_error($conn);
		}
	}	
}
else{
	echo "Something went wrong. Please try again later.";
}

?>

		<p><a href="index.php">Home</a></p>
		<a href="logout.php">Logout</a>
	</body>
</html>

