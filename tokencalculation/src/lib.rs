use rust_decimal::Decimal;
use rust_decimal::prelude::*;
use rust_decimal::RoundingStrategy;
use std::ffi::{CStr, CString};
use std::os::raw::c_char;

#[inline]
fn get_decimal(ptr: *const c_char) -> Decimal {
    if ptr.is_null() {
        return Decimal::ZERO;
    }
    let c_str = unsafe { CStr::from_ptr(ptr) };
    let str_val = c_str.to_str().unwrap_or("0");
    Decimal::from_str(str_val).unwrap_or(Decimal::ZERO)
}

#[inline]
fn truncate_10(d: Decimal) -> Decimal {
    d.round_dp_with_strategy(10, RoundingStrategy::ToZero)
}

#[inline]
fn decimal_result_to_cstr(result: Decimal) -> *const c_char {
    let result_str = result.to_string();
    let c_string = CString::new(result_str).unwrap();
    c_string.into_raw()
}

#[no_mangle]
pub extern "C" fn add_decimal(a: *const c_char, b: *const c_char) -> *const c_char {
    let result = truncate_10(get_decimal(a) + get_decimal(b));
    decimal_result_to_cstr(result)
}

#[no_mangle]
pub extern "C" fn subtract_decimal(a: *const c_char, b: *const c_char) -> *const c_char {
    let result = truncate_10(get_decimal(a) - get_decimal(b));
    decimal_result_to_cstr(result)
}

#[no_mangle]
pub extern "C" fn multiply_decimal(a: *const c_char, b: *const c_char) -> *const c_char {
    let result = truncate_10(get_decimal(a) * get_decimal(b));
    decimal_result_to_cstr(result)
}

#[no_mangle]
pub extern "C" fn divide_decimal(a: *const c_char, b: *const c_char) -> *const c_char {
    let divisor = get_decimal(b);
    if divisor.is_zero() {
        let err = CString::new("ERR_DIV0").unwrap();
        return err.into_raw();
    }
    let result = truncate_10(get_decimal(a) / divisor);
    decimal_result_to_cstr(result)
}

/*
(Optional) Provide a free function for the caller to deallocate the returned C string.
Call this from the host side once you're done with the result pointer.

#[no_mangle]
pub extern "C" fn free_c_string(ptr: *mut c_char) {
    if !ptr.is_null() {
        unsafe { let _ = CString::from_raw(ptr); }
    }
}
*/