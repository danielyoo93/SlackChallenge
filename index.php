<?php

header('Content-Type: application/json');

include 'DB_connect.php';
include 'TicTacToeController.php';

	//connect to database
	$connection = DB_connect::connect();

	$controller = new TicTacToeController();
	$token 		= $_POST['token'];
	$channelId 	= $_POST['channel_id'];
	$user		= $_POST['user_name'];
	$command	= $_POST['text'];

	//verify token from Slack
	$controller->verifyToken($token);

	//displays command descriptions to the user
	if ($command == '!help') {
		$message = "Available commands: \n \"/ttt username\" to start a game against this player,\n \"/ttt display\" to show the current board state,\n \"/ttt play[row][column]\" where [row]= row and [column] = column that you want to play your move. (22 is the center of the board)";

		echo HttpHelper::genericResponse($message);

		return true;
	}

	//verifies if a game is currently being played on the users channel
	if (!$controller->verifyExistingGame($connection, $channelId)) {
		echo $controller->initializeGame($connection, $user, $command, $channelId);

		return true;
	}

	//commands
	if (substr($command, 0, 4) == 'play') {
		echo $controller->playMove($connection, $user, $channelId, $command);

		return true;
	}

	if ($command == 'display') {
		echo $controller->displayBoard($connection, $channelId);

		return true;
	}

	echo HttpHelper::genericResponse("Invalid command. A game is currently in progress!");

	DB_connect::close($connection);
?>