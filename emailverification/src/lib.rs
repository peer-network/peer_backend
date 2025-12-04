use check_if_email_exists::{check_email, CheckEmailInputBuilder, CheckEmailOutput};
use serde_json::json;
use std::ffi::{CStr, CString};
use std::os::raw::c_char;

/// Perform a verification for the provided email address and return the
/// serialized JSON output. The JSON has the following shape:
/// `{ "status": "ok", "data": <CheckEmailOutput> }` on success or
/// `{ "status": "error", "message": "...reason..." }` on failure.
#[no_mangle]
pub extern "C" fn verify_email_json(email: *const c_char) -> *mut c_char {
	match verify_email_internal(email) {
		Ok(output) => json_ok(output),
		Err(message) => json_error(&message),
	}
}

/// Free a heap allocated C string returned by this library.
#[no_mangle]
pub extern "C" fn free_verification_result(ptr: *mut c_char) {
	if ptr.is_null() {
		return;
	}
	unsafe {
		let _ = CString::from_raw(ptr);
	}
}

fn verify_email_internal(email: *const c_char) -> Result<CheckEmailOutput, String> {
	let email = if email.is_null() {
		return Err("Email pointer was null".to_string());
	} else {
		unsafe { CStr::from_ptr(email) }
	};

	let email = email
		.to_str()
		.map_err(|_| "Email contained invalid UTF-8 data".to_string())?;

	let input = CheckEmailInputBuilder::default()
		.to_email(email.to_string())
		.build()
		.map_err(|err| format!("Failed to build verification request: {err}"))?;

	let runtime = tokio::runtime::Builder::new_multi_thread()
		.enable_all()
		.build()
		.map_err(|err| format!("Failed to create Tokio runtime: {err}"))?;

	let output = runtime.block_on(async { check_email(&input).await });

	Ok(output)
}

fn json_ok(output: CheckEmailOutput) -> *mut c_char {
	let payload = json!({
		"status": "ok",
		"data": output,
	});
	to_c_string(payload.to_string())
}

fn json_error(message: &str) -> *mut c_char {
	let payload = json!({
		"status": "error",
		"message": message,
	});
	to_c_string(payload.to_string())
}

fn to_c_string(value: String) -> *mut c_char {
	CString::new(value)
		.unwrap_or_else(|_| CString::new("{\"status\":\"error\",\"message\":\"CString failure\"}").unwrap())
		.into_raw()
}
