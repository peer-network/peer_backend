import { CodeDescription } from '../utils/errors';
import { IClientException } from '../utils/errors/IClientException';
import { logger } from '../utils/logger';

export default class CoreClientResponse<TData> {
  public readonly status: string;
  public readonly responseCode: string;
  public readonly message: string;
  public readonly data: TData | null;  
//   public readonly timestamp: number;
  
  static successCodeDescription : CodeDescription = {
    status : "success",
    code: "10000",
    message: "success"
  }

  constructor(
    status : string,
    responseCode: string, 
    responseMessage: string, 
    data?: TData
  ) {
    // logger.error(error.message, detailsMessage)
    this.status      = status;
    this.responseCode      = responseCode;
    this.message   = responseMessage;
    this.data      = data || null;
    // this.timestamp = Date.now();
  }
  
  public static success<TData>(data?: TData,optionalMessage? : string, codeDescription: CodeDescription = this.successCodeDescription): CoreClientResponse<TData> {
    const resultCode: string = codeDescription.code;
    const resultStatus: string = codeDescription.status;
    const resultMessage: string = optionalMessage || codeDescription.message;
    logger.info(
      resultCode,
      resultMessage
    )
    return new CoreClientResponse(resultStatus, resultCode, resultMessage, data);
  }

  public static error<TData>(clientException: IClientException , optionalMessage? : string,data?: TData): CoreClientResponse<TData>;
  public static error<TData>(codeDescription: CodeDescription , optionalMessage? : string,data?: TData): CoreClientResponse<TData>;
  public static error<TData>(error: CodeDescription | IClientException , optionalMessage : string = "",data?: TData): CoreClientResponse<TData> {
    const resultCode: string = error.code;
    const resultStatus: string = "error";
    const resultMessage: string = error.message + " " + optionalMessage;
    logger.error(
      resultCode,
      resultMessage
    )
    return new CoreClientResponse(resultStatus,resultCode, resultMessage, data);
  }
}