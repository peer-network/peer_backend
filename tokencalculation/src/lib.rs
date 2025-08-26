use rust_decimal::Decimal;
use rust_decimal::prelude::*;
use std::ffi::{CStr, CString};
use std::os::raw::c_char;

fn get_decimal(ptr: *const c_char) -> Decimal {
    let c_str = unsafe { CStr::from_ptr(ptr) };
    let str_val = c_str.to_str().unwrap_or("0");
    Decimal::from_str(str_val).unwrap_or(Decimal::ZERO)
}

fn decimal_result_to_cstr(result: Decimal) -> *const c_char {
    let result_str = result.to_string();
    let c_string = CString::new(result_str).unwrap();
    c_string.into_raw()
}

#[no_mangle]
pub extern "C" fn add_decimal(a: *const c_char, b: *const c_char) -> *const c_char {
    let result = get_decimal(a) + get_decimal(b);
    decimal_result_to_cstr(result)
}

#[no_mangle]
pub extern "C" fn subtract_decimal(a: *const c_char, b: *const c_char) -> *const c_char {
    let result = get_decimal(a) - get_decimal(b);
    decimal_result_to_cstr(result)
}

#[no_mangle]
pub extern "C" fn multiply_decimal(a: *const c_char, b: *const c_char) -> *const c_char {
    let result = get_decimal(a) * get_decimal(b);
    decimal_result_to_cstr(result)
}

#[no_mangle]
pub extern "C" fn divide_decimal(a: *const c_char, b: *const c_char) -> *const c_char {
    let divisor = get_decimal(b);
    if divisor.is_zero() {
        let err = CString::new("ERR_DIV0").unwrap();
        return err.into_raw();
    }

    let result = get_decimal(a) / divisor;
    decimal_result_to_cstr(result)
}