<?php

include 'HttpHelper.php';

class TicTacToeController
{
	/**
	 * Verifies Slack token
	 *
	 * @param string $token
	 * @author d_yoo
	 */
	public function verifyToken($token)
	{
		if ($token != 'sZHWJiYFGt8pMmsND2KqLRpm') {
			die("What are you doing here >:( You need permission!");
		}
	}

	/**
	 * Verifies if there is currently a game being played in given channel
	 *
	 * @param $connection
	 * @param $channelId
	 * @return boolean
	 * @author d_yoo
	 */
	public function verifyExistingGame($connection, $channelId)
	{
		$query  = "SELECT * FROM public.tictactoe WHERE channel_id = '" . $channelId . "'";
		$result = pg_query($connection, $query);
		$row 	= pg_fetch_array($result);

		if (empty($row)) {
			return false;
		}

		return true;
	}

	/**
	 * Initializes a tic-tac-toe game for the channel
     *
	 * @param $connection
	 * @param string $playerOne
	 * @param string $playerTwo
	 * @param string $channelId
	 * @return HttpHelper::gameStartResponse
	 * @author d_yoo
	 */
	public function initializeGame($connection, $playerOne, $playerTwo, $channelId)
	{
		//slackbot is too stupid to play tic-tac-toe
		if ($playerTwo == 'slackbot') {
			return HttpHelper::genericResponse("You can't play against slackbot : (");
		}
		
		//validate that player 2 is indeed a user in the current channel
		//check if playerTwo is in the member list. if yes, get the id. if not return false.
		$members     = HttpHelper::getMembersList();
		$playerTwoId = $this::getPlayerId($members, $playerTwo);
		
		if (empty($playerTwoId)) {
			return HttpHelper::genericResponse("This user doesn't exist!");
		}

		if (!$this::validatePlayerIsInChannel($playerTwoId, $channelId)) {
			return HttpHelper::genericResponse("This user is not in this channel! You can only play from channels.");
		}

		$row = array(
			'player_1' 		=> $playerOne,
			'player_2' 		=> $playerTwo,
			'channel_id' 	=> $channelId,
			'turn' 			=> 1
		);

		pg_insert($connection, 'public.tictactoe', $row);

		return HttpHelper::gameStartResponse($playerOne, $playerTwo);
	}

	/**
	 * Validates if the given user id is in the channel
	 *
	 * @param string $playerId
	 * @param string $channelId
	 * @return boolean
	 * @author d_yoo
	 */
	public function validatePlayerIsInChannel($playerId, $channelId)
	{
		$memberIds = HttpHelper::getMembersInChannel($channelId);

		foreach ($memberIds as $memberId) {
			if ($memberId == $playerId) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gets the member id given the member name
	 *
	 * @param array $members
	 * @param string $playerName
	 * @return string $memberId
	 * @author d_yoo
	 */
	public function getPlayerId($members, $playerName)
	{
		foreach ($members as $member) {
			if ($member['name'] == $playerName) {
				return $member['id'];
			}
		}
		
		return '';
	}

	/**
	 * Given the board, checks if the move caused a winning move
	 *
	 * @param array $board row date from the db table
	 * @param string $rowPlayed
	 * @param string $columnPlayed
	 * @param char $symbol
	 * @return boolean
	 * @author d_yoo
	 */
	public function isWinning($board, $rowPlayed, $columnPlayed, $symbol)
	{
		//checks rows
        if ($board["r" . $rowPlayed . "_c1"] == $symbol &&
            $board["r" . $rowPlayed . "_c2"] == $symbol &&
            $board["r" . $rowPlayed . "_c3"] == $symbol) {
            return true;
        }

        //checks columns
        if ($board["r1_c" . $columnPlayed] == $symbol &&
            $board["r2_c" . $columnPlayed] == $symbol &&
            $board["r3_c" . $columnPlayed] == $symbol) {
            return true;
        }

        //check diagonals 
        if (($rowPlayed != 2 && $columnPlayed != 2) || ($rowPlayed == 2 && $columnPlayed == 2)) {
            if ($board['r1_c1'] == $symbol &&
                $board['r2_c2'] == $symbol &&
                $board['r3_c3'] == $symbol) {
                return true;
            }

            if ($board['r1_c3'] == $symbol &&
                $board['r2_c2'] == $symbol &&
                $board['r3_c1'] == $symbol) {
                return true;
            }
        }

        return false;
	}

	/**
	 * Given the board, checks if the game is a tie
	 *
	 * @param array $board
	 * @return boolean
	 * @author d_yoo
	 */
	public function isTie($board)
	{
		foreach ($board as $tile) {
			if (empty($tile)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Displays the board state
	 *
	 * @param $connection
     * @param $channelId
	 * @return HttpHelper::displayResponse
	 * @author d_yoo
	 */
	public function displayBoard($connection, $channelId)
	{
		if ($this::verifyExistingGame($connection, $channelId)) {
			$query 	= "SELECT * FROM public.tictactoe WHERE channel_id = '" . $channelId . "'";
			$result = pg_query($connection, $query);
			$row 	= pg_fetch_array($result, 0, PGSQL_BOTH);

			return HttpHelper::displayResponse("This is the current board", $row, "good");
		}

		return HttpHelper::displayResponse("There is no game in this channel", null, "good");;
	}

	/**
	 * Inserts the users move into db and displays message accordingly
	 *
	 * @param $connection
	 * @param string $user
	 * @param string $channelId
	 * @param string $command
	 * @return HttpHelper::genericResponse()
	 * @author d_yoo
	 */
	public function playMove($connection, $user, $channelId, $command)
	{
		$query 	= "SELECT * FROM public.tictactoe WHERE channel_id = '" . $channelId . "'";
		$result = pg_query($connection, $query);
		$row 	= pg_fetch_array($result, 0, PGSQL_BOTH);

		//check if it actually is the users turn
		if ($row['turn'] == 1) {
			$symbol = "x";
			$turn 	= 2;

			if ($user != $row['player_1']) {
				return HttpHelper::genericResponse("It isn't your turn to play!");
			}
		} else {
			$symbol = "o";
			$turn 	= 1;	

			if ($user != $row['player_2']) {
				return HttpHelper::genericResponse("It isn't your turn to play!");
			}
		}

		//validate their entry
		$inputRow	 = substr($command, 4, 1);
		$inputColumn = substr($command, 5, 1);
		$inputString = "r" . $inputRow . "_c" . $inputColumn;

		if (1 <= $inputRow && $inputRow <= 3 && 1 <= $inputColumn && $inputColumn <= 3) {

			//check if the coordinates is a playable spot or not
			if (!empty($row[$inputString])) {
				return HttpHelper::genericResponse("This spot was already played on!");
			}

			//insert into the table.
			$data 		= array('turn' => $turn, $inputString => $symbol);
			$condition 	= array('channel_id' => $channelId);
			$update 	= pg_update($connection, 'tictactoe', $data, $condition);

			$displayResult = pg_query($connection, $query);
			$displayRow    = pg_fetch_array($displayResult, 0, PGSQL_BOTH);

			//check if this was a winning move. if yes, delete the row, print message
			if ($this->isWinning($displayRow, $inputRow, $inputColumn, $symbol)) {
				$this::destroyBoard($connection, $channelId);

				$winMessage =  $user . " has won!";

				return HttpHelper::displayResponse($winMessage, $displayRow, "good", false);
			}

			//checks if it is a tie
			if ($this->isTie($displayRow)) {
				$this::destroyBoard($connection, $channelId);

				$tieMessage = "Game has ended as a tie! Good game!";

				return HttpHelper::displayResponse($tieMessage, $displayRow, "good", false);
			}

			//print a normal response to confirm the move
			return HttpHelper::displayResponse("Good move!", $displayRow, "good");
		}
	}

	/**
	 * Method that removes the game from the datatable
	 *
	 * @param $connection
	 * @param int $channelId
	 * @author d_yoo
	 */
	public function destroyBoard($connection, $channelId)
	{
		pg_delete($connection, 'tictactoe', ["channel_id" => $channelId]);
	}
}

?>