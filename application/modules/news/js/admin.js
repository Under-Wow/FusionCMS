var News = {
	imagesloader: null,
	selectedType: 0,
	remove: function(id, element)
	{
		var row = $(element).parents("tr");
		Swal.fire({
		title: 'Are you sure?',
		icon: 'warning',
		showCancelButton: true,
		confirmButtonColor: '#3085d6',
		cancelButtonColor: '#d33',
		confirmButtonText: 'Yes, delete it!'
		}).then((result) => {
		if (result.isConfirmed) {
			Swal.fire(
				'Deleted!',
				'',
				'success'
			)
			$("#page_count").html(parseInt($("#page_count").html()) - 1);

			row.hide(300, function() {
				row.remove();
			});

			$.get(Config.URL + "news/admin/delete/" + id);
		}
		})
	},

	show: function()
	{
		if($("#news_articles").is(":visible"))
		{
			$("#news_articles").fadeOut(100, function()
			{
				$('#add_news').fadeIn(100);
			});
		}
		else
		{
			$("#add_news").fadeOut(100, function()
			{
				$('#news_articles').fadeIn(100);
			});
		}
	},

	previewImages: function(input) {
		$('#image_preview').html("");
		if(input.files) {
			for(let file of input.files) {
				if(file) {
					var ext = file.type.split("/")[1];
					if(ext == "gif" || ext == "png" || ext == "jpeg" || ext == "jpg") {
						var reader = new FileReader();
						reader.onload = function (e) {
							$('#image_preview').append("<img src='"+ e.target.result +"'>");
							console.log(e.target.result);
						}
						reader.readAsDataURL(file);
					}
					console.log(file);
				}
			}
		}
	},

	send: function(form, id)
	{
		tinyMCE.triggerSave();

		let headlineData = {};
		const $headlines = document.querySelectorAll('[__headline__]');

		[...$headlines].map((item, index) => {
			headlineData[item.getAttribute('__headline__')] = item.value;
		});

		let contentData = {};
		const $contents = document.querySelectorAll('[__content__]');

		[...$contents].map((item, index) => {
			contentData[item.getAttribute('__content__')] = item.value;
		});

		var files =  News.imagesloader.data('format.imagesloader').AttachmentArray;
		var il =  News.imagesloader.data('format.imagesloader');
		var fd = new FormData();

		if(News.selectedType == 1) {
			if (il.CheckValidity()) {
				var fileNames = [];
				for(var file of files) {
					console.log(file);
					fd.append("type_image[]", file["File"]);
					fileNames.push(file["FileName"]);
				}

				fd.append("fileNames", fileNames);
			} else {
				return;
			}
		}

        fd.append("type",            $("#type").val());
        fd.append("avatar",          $("#avatar").is(":checked"));
        fd.append("comments",        $("#comments").is(":checked"));
        fd.append("headline",     	JSON.stringify(headlineData));
        fd.append("content",      	JSON.stringify(contentData));
        fd.append("csrf_token_name", Config.CSRF);
        fd.append("type_content",    "");
        fd.append("type_video",      $('#type_video').val());

		for (var pair of fd.entries()) {
			console.log(pair[0]+ ', ' + pair[1]); 
		}

		$.ajax({
			url: Config.URL + "news/admin/create" + ((id) ? "/" + id : ""), 
			type: 'post',
			data: fd,
			dataType: 'text',
			contentType: false,
			processData: false,
			success: function (response) {
				if(response == "yes")	{
					console.log(response);
					window.location = Config.URL + "news/admin";
				} else {
					console.log(response);
					Swal.fire({
						icon: 'error',
						title: 'Oops...',
						text: (response),
					})
				}
			}
		});
	},
	
	changeType: function(element)
	{
		switch(element.value)
		{
			case "0":
				$("#video, #image").hide();
				$("#type_video, #type_image").val("");
				News.selectedType = 0;
			break;

			case "1":
				$("#video").hide();
				$("#type_video").val("");
				$("#image").show();
				News.selectedType = 1;
			break;

			case "2":
				$("#image").hide();
				$("#type_image").val("");
				$("#video").show();
				News.selectedType = 2;
			break;
		}
	},
}
	