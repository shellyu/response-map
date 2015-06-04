<?php
	function return_bytes($val) {
		$val = trim($val);
		$last = strtolower($val[strlen($val)-1]);
		switch($last) {
			// The 'G' modifier is available since PHP 5.1.0
			case 'g':
				$val *= 1024;
			case 'm':
				$val *= 1024;
			case 'k':
				$val *= 1024;
		}

		return $val;
	}

	$assigned_filename = md5($_SESSION[$_POST['lis_result_sourcedid']]['user_id'] . $question_id);
?>
<html>
	<head>
		<?php include('html/header.html'); ?>

		<script>
			/*jslint unparam: true */
			/*global window, $ */
			$(function () {
				'use strict';
				// Change this to the location of your server-side upload handler:
				var url = 'upload.php?user_id=<?php echo $assigned_filename; ?>';
				$('#fileupload').fileupload({
					url: url,
					dataType: 'json',
					done: function (e, data) {
						console.log(data.result.imagefile);
						$.each(data.result.imagefile, function (index, file) {
							if(file.error) {
								$('#errors').html('<p>Error: '+file.error+'</p>');
							} else {
								$('#errors').html('');
								$('#image-preview').attr('src', data.result.imagefile[0].thumbnailUrl);
								//$('#player').attr('src','videoplayer.php?user_id=<?php //echo $hashedplayer_id; ?>');
								$('#uploadtext').text('');

								$('.image-url').val(data.result.imagefile[0].url);
								$('.thumbnail-url').val(data.result.imagefile[0].thumbnailUrl);
							}
						});
					},
					progressall: function (e, data) {
						var progress = parseInt(data.loaded / data.total * 100, 10);
						$('#progress .progress-bar').css(
							'width',
							progress + '%'
						);
					}
				}).prop('disabled', !$.support.fileInput)
					.parent().addClass($.support.fileInput ? undefined : 'disabled');
			});
			function fixload() {
				$('iframe').load(function() {
					$('#player').height(''+(this.contentWindow.document.body.offsetHeight));
				});
			}
			fixload();
		</script>
	</head>
	
	<form action="index.php" method="post">
		<input class="question-did" name="lis_result_sourcedid" value="<?php echo $_POST['lis_result_sourcedid'] ?>">

		<div class="input-group">
			<span class="input-group-addon">Name</span>
			<input type="text" class="form-control user-fullname" name="user_fullname">
		</div>
		<div class="input-group">
			<span class="input-group-addon">Location</span>
			<input type="text" class="form-control user-location" name="user_location">
		</div>
		<div class="input-group">
			<?php $response_label = 'Response'; ?>
			<?php 
			if(isset($_POST['custom_responsetext'])) { 
				$response_label = $_POST['custom_responsetext'];
			}	
			?>
			<span class="input-group-addon"><?php echo $response_label; ?></span>
			<textarea class="form-control user-response" rows="5" name="user_response"></textarea>
		</div>

		<div class="upload-group">
			<!-- The fileinput-button span is used to style the file input field as button -->
			<span class="filelimit"><?php echo 'Maximum file size is ' . (return_bytes(ini_get('post_max_size')) / 1024 / 1024) . ' MB'; ?></span>
			<span class="btn btn-primary fileinput-button">
				<i class="glyphicon glyphicon-plus"></i> Upload Image
				<!--<span id="uploadtext"><?php //echo $selecttext; ?></span>-->
				<!-- The file input field used as target for the file upload widget -->
				<input id="fileupload" type="file" name="imagefile">
			</span>

			<!-- The global progress bar -->
			<div id="progress" class="progress">
				<div class="progress-bar progress-bar-success"></div>
			</div>
			<!-- The container for the uploaded files -->
			<div id="errors" class="error"></div>
			<div class="panel panel-default">
				<div class="panel-heading">
					<span>Preview</span>
				</div>
				<div class="panel-body">
					<img id="image-preview">
				</div>
			</div>
		</div>

		<input type="text" class="image-url" name="user_image_url">
		<input type="text" class="thumbnail-url" name="user_thumbnail_url">
		
		<input type="hidden" name="ltifix_user_id" value="<?php echo $_SESSION[$_POST['lis_result_sourcedid']]['user_id']; ?>" />

		<button type="submit" class="save-question btn btn-primary">Save</button>
	</form>
</html>