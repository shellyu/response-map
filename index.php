<?php
	if($_POST['session_id']) {
		$session_id = $_POST['session_id'];
		session_id($_POST['session_id']);
		session_start();
	}
	else {
		session_start();
		$session_id = session_id();
	}

	require_once('lti.php');

	$google_key = 'AIzaSyCLwZ7CFtSTjbZn3Xnw_8_038o1tisTxss';

	/* Check if a valid lti request received */
	$lti = new Lti();
	$lti->require_valid(); // Returns error message if not a valid LTI request

	$lis_result_sourcedid_split = explode(':', $_POST['lis_result_sourcedid']);
	$question_url_id = explode('-', $lis_result_sourcedid_split[1]);
	$question_id = $question_url_id[count($question_url_id) - 1];

	require_once('config.php');

	if (mysqli_connect_error()) {
		echo 'Failed to connect to question database: ' . mysqli_connect_error();
		die();
	}

	// Check that response table exists and create if not
	if (mysqli_num_rows(mysqli_query($conn, 'SHOW TABLES like "response"')) === 0) {
		$sql = "CREATE table response (
			response_id int NOT NULL AUTO_INCREMENT,
			resource_id varchar(500) NOT NULL,
			response_body varchar(50000),
			user_id varchar(500) NOT NULL,
			image_url varchar(2000),
			thumbnail_url varchar(2000),
			create_time timestamp,
			PRIMARY KEY (response_id)
		)";
			
		if (!mysqli_query($conn, $sql)) {
			echo 'Cannot create config table! Please contact UQx staff.';
			die();
		}
	}

	// Check that config table exists and create if not
	if (mysqli_num_rows(mysqli_query($conn, 'SHOW TABLES like "user"')) === 0) {
		$sql = "CREATE table user (
			user_id varchar(500) NOT NULL,
			fullname varchar(500) NOT NULL,
			location varchar(500) NOT NULL,
			lat DECIMAL(10, 8) NOT NULL,
			lng DECIMAL(11, 8) NOT NULL,
			create_time timestamp,
			PRIMARY KEY (user_id)
		)";
			
		if (!mysqli_query($conn, $sql)) {
			echo 'Cannot create user table! Please contact UQx staff.';
			die();
		}
	}

	$student_responses = array();
	$all_text = '';
	$display_name_loc = true;

	require_once('process-text.php');

	// Ensure that the name, location and response are http and quote escaped
	$_POST = escapeUserInput($_POST);

	// Check to see if student has submitted fullname and location
	$select_user_query = mysqli_query($conn, 'SELECT fullname, location, lat, lng FROM user WHERE user_id = "' . $_SESSION[$_POST['lis_result_sourcedid']]['user_id'] . '"');
	$user_row = mysqli_fetch_row($select_user_query);

	if (!empty($user_row)) {
		$select_response_query = mysqli_query($conn, 'SELECT response_body, image_url, thumbnail_url FROM response WHERE resource_id = "' . $question_id . '" AND user_id = "' . $_SESSION[$_POST['lis_result_sourcedid']]['user_id'] . '"');

		$self_row = mysqli_fetch_row($select_response_query);
		if ((!empty($self_row)) || (!empty($_POST['user_response']))) {
			$student_responses[0] = new stdClass();

			if (empty($self_row)) {
				$insert_response_query = mysqli_query($conn, 'INSERT INTO response (resource_id, response_body, user_id, create_time) VALUES ("' . $question_id . '", "' . $_POST['user_response'] . '", "' . $_SESSION[$_POST['lis_result_sourcedid']]['user_id'] . '", NOW())');

				if (!empty($_POST['user_image_url']) && !empty($_POST['user_thumbnail_url'])) {
					$edit_response_query = mysqli_query($conn, 'UPDATE response SET image_url = "' . $_POST['user_image_url'] . '", thumbnail_url = "' . $_POST['user_thumbnail_url'] . '" WHERE resource_id = "' . $question_id . '" AND user_id = "' . $_SESSION[$_POST['lis_result_sourcedid']]['user_id'] . '"');
				}

				// Manually put student's own response into the array to be passed to Google maps
				$student_responses[0]->response = $_POST['user_response'];
				$student_responses[0]->image_url = $_POST['user_image_url'];
				$student_responses[0]->thumbnail_url = $_POST['user_thumbnail_url'];
			}
			else {
				// Manually put student's own response into the array to be passed to Google maps
				$student_responses[0]->response = $self_row[0];
				$student_responses[0]->image_url = $self_row[1];
				$student_responses[0]->thumbnail_url = $self_row[2];
			}

			$all_text .= ' ' . $student_responses[0]->response;

			$student_responses[0]->fullname = $user_row[0];
			$student_responses[0]->location = $user_row[1];
			$student_responses[0]->lat = $user_row[2];
			$student_responses[0]->lng = $user_row[3];

			$select_response_query = mysqli_query($conn, 'SELECT response_body, fullname, location, lat, lng, image_url, thumbnail_url FROM response, user WHERE resource_id = "' . $question_id . '" AND response.user_id = user.user_id AND user.user_id != "' . $_SESSION[$_POST['lis_result_sourcedid']]['user_id'] . '"');

			// Loop through all other student responses obtained from database and push it into array
			while ($others_row = mysqli_fetch_row($select_response_query)) {
				$student_response = new stdClass();
				$student_response->response = $others_row[0];
				$student_response->fullname = $others_row[1];
				$student_response->location = $others_row[2];
				$student_response->lat = $others_row[3];
				$student_response->lng = $others_row[4];
				$student_response->image_url = $others_row[5];
				$student_response->thumbnail_url = $others_row[6];

				$all_text .= ' ' . $student_response->response;

				array_push($student_responses, $student_response);
			}

			$all_student_responses = json_encode($student_responses);
			$word_frequency = json_encode(wordCount($all_text));

			// Show map
			require('map.php');
		}
		else {
			// Hide the fullname and location fields
			$display_name_loc = false;

			// Show response form
			require('response.php');
		}
	}
	elseif (!empty($_POST['user_fullname']) && !empty($_POST['user_location'])) {
		$geocode = JSON_decode(file_get_contents("https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($_POST['user_location']) . "&sensor=false&key=" . $google_key));

		if ($geocode->status === 'OK') {
			$insert_user_query = mysqli_query($conn, 'INSERT INTO user (user_id, fullname, location, lat, lng, create_time) VALUES ("' . $_SESSION[$_POST['lis_result_sourcedid']]['user_id'] . '", "' . $_POST['user_fullname'] . '", "' . $_POST['user_location'] . '", ' . $geocode->results[0]->geometry->location->lat . ', ' . $geocode->results[0]->geometry->location->lng . ', NOW())');

			if ($insert_user_query) {
				if (!empty($_POST['user_response'])) {
					$select_response_query = mysqli_query($conn, 'SELECT response_body, image_url, thumbnail_url FROM response WHERE resource_id = "' . $question_id . '" AND user_id = "' . $_SESSION[$_POST['lis_result_sourcedid']]['user_id'] . '"');
					$self_row = mysqli_fetch_row($select_response_query);
					$student_responses[0] = new stdClass();

					if (empty($self_row)) {
						$insert_response_query = mysqli_query($conn, 'INSERT INTO response (resource_id, response_body, user_id, create_time) VALUES ("' . $question_id . '", "' . $_POST['user_response'] . '", "' . $_SESSION[$_POST['lis_result_sourcedid']]['user_id'] . '", NOW())');

						if (!empty($_POST['user_image_url']) && !empty($_POST['user_thumbnail_url'])) {
							$edit_response_query = mysqli_query($conn, 'UPDATE response SET image_url = "' . $_POST['user_image_url'] . '", thumbnail_url = "' . $_POST['user_thumbnail_url'] . '" WHERE resource_id = "' . $question_id . '" AND user_id = "' . $_SESSION[$_POST['lis_result_sourcedid']]['user_id'] . '"');
						}

						$student_responses[0]->response = $_POST['user_response'];
						$student_responses[0]->image_url = $_POST['user_image_url'];
						$student_responses[0]->thumbnail_url = $_POST['user_thumbnail_url'];
					}
					else {
						// Should not ever be here
						$student_responses[0]->response = $self_row[0];
						$student_responses[0]->image_url = $self_row[1];
						$student_responses[0]->thumbnail_url = $self_row[2];
					}

					$all_text .= ' ' . $student_responses[0]->response;

					$student_responses[0]->fullname = $_POST['user_fullname'];
					$student_responses[0]->location = $_POST['user_location'];
					$student_responses[0]->lat = $geocode->results[0]->geometry->location->lat;
					$student_responses[0]->lng = $geocode->results[0]->geometry->location->lng;

					$select_response_query = mysqli_query($conn, 'SELECT response_body, fullname, location, lat, lng, image_url, thumbnail_url FROM response, user WHERE resource_id = "' . $question_id . '" AND response.user_id = user.user_id AND user.user_id != "' . $_SESSION[$_POST['lis_result_sourcedid']]['user_id'] . '"');

					// Loop through all other student responses obtained from database and push it into array
					while ($others_row = mysqli_fetch_row($select_response_query)) {
						$student_response = new stdClass();
						$student_response->response = $others_row[0];
						$student_response->fullname = $others_row[1];
						$student_response->location = $others_row[2];
						$student_response->lat = $others_row[3];
						$student_response->lng = $others_row[4];
						$student_response->image_url = $others_row[5];
						$student_response->thumbnail_url = $others_row[6];

						$all_text .= ' ' . $student_response->response;

						array_push($student_responses, $student_response);
					}

					$all_student_responses = json_encode($student_responses);
					$word_frequency = json_encode(wordCount($all_text));

					require('map.php');
				}
				else {
					$display_name_loc = false;
					require('response.php');
				}
			}
			else {
				require('response.php');
			}
		}
		else {
			require('response.php');
		}
	}
	else {
		require('response.php');
	}
?>