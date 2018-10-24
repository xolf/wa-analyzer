# WhatsApp Analyzer

## Usage
`php analyzer.php [CHAT-LOG.txt] [TIME-FORMAT]`.

### `[CHAT-LOG.txt]`
Must be an `.txt` export file from an WhatsApp Chat. (excluded media)

### `[TIME-FORMAT]` _optional_
Groups the messages in the timeline by the given time format. For valid formats see http://php.net/manual/de/function.date.php.  
Default is: `W#Y`

Following shortcuts are supported
`week` for `W#Y`  
`dayofweek` or `dow` for `D`  
`month` for `m.Y`  
`monthofyear` for `F`  
`year` for `Y`  
