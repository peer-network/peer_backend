use rust_decimal::Decimal;
use rust_decimal::prelude::*;
use rust_decimal::RoundingStrategy;
use std::ffi::{CStr, CString};
use std::os::raw::c_char;

#[inline]
fn get_decimal(ptr: *const c_char) -> ResultDecimal {
    if ptr.is_null() {
        return Err(ParseErr::Null);
    }
    // SAFETY: caller must pass a valid, NUL-terminated C string.
    let c_str = unsafe { CStr::from_ptr(ptr) };
    let s = match c_str.to_str() {
        Ok(v) => v,
        Err(_) => return Err(ParseErr::Utf8),
    };
    Decimal::from_str(s).map_err(|_| ParseErr::Syntax)
}

#[inline]
fn truncate_10(d: Decimal) -> Decimal {
    d.round_dp_with_strategy(10, RoundingStrategy::ToZero)
}

#[inline]
fn ok_str(s: &str) -> *mut c_char {
    CString::new(s).unwrap_or_else(|_| CString::new("ERR_NUL").unwrap()).into_raw()
}

#[inline]
fn ok_decimal(d: Decimal) -> *mut c_char {
    ok_str(&truncate_10(d).to_string())
}

#[no_mangle]
pub extern "C" fn add_decimal(a: *const c_char, b: *const c_char) -> *mut c_char {
    let a = match get_decimal(a) { Ok(v) => v, Err(e) => return ok_str(e.code()) };
    let b = match get_decimal(b) { Ok(v) => v, Err(e) => return ok_str(e.code()) };
    ok_decimal(a + b)
}

#[no_mangle]
pub extern "C" fn subtract_decimal(a: *const c_char, b: *const c_char) -> *mut c_char {
    let a = match get_decimal(a) { Ok(v) => v, Err(e) => return ok_str(e.code()) };
    let b = match get_decimal(b) { Ok(v) => v, Err(e) => return ok_str(e.code()) };
    ok_decimal(a - b)
}

#[no_mangle]
pub extern "C" fn multiply_decimal(a: *const c_char, b: *const c_char) -> *mut c_char {
    let a = match get_decimal(a) { Ok(v) => v, Err(e) => return ok_str(e.code()) };
    let b = match get_decimal(b) { Ok(v) => v, Err(e) => return ok_str(e.code()) };
    ok_decimal(a * b)
}

#[no_mangle]
pub extern "C" fn divide_decimal(a: *const c_char, b: *const c_char) -> *mut c_char {
    let a = match get_decimal(a) { Ok(v) => v, Err(e) => return ok_str(e.code()) };
    let b = match get_decimal(b) { Ok(v) => v, Err(e) => return ok_str(e.code()) };
    if b.is_zero() { return ok_str("ERR_DIV0"); }
    ok_decimal(a / b)
}

#[no_mangle]
pub extern "C" fn truncate_decimal(a: *const c_char) -> *mut c_char {
    // No arithmetic: parse -> truncate to 10 dp -> return string
    let a = match get_decimal(a) { Ok(v) => v, Err(e) => return ok_str(e.code()) };
    ok_decimal(a)
}


#[no_mangle]
pub extern "C" fn free_c_string(ptr: *mut c_char) {
    if !ptr.is_null() {
        unsafe { let _ = CString::from_raw(ptr); }
    }
}

// --- helpers ---
enum ParseErr { Null, Utf8, Syntax }
type ResultDecimal = Result<Decimal, ParseErr>;
impl ParseErr {
    fn code(&self) -> &'static str {
        match self {
            ParseErr::Null => "ERR_NULL",
            ParseErr::Utf8 => "ERR_UTF8",
            ParseErr::Syntax => "ERR_PARSE",
        }
    }
}