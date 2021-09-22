<p>
  <h1 align="center">Maxon Reporter</h1>
  <p align="center">Machine Extensible Monitoring - Reporter</p>
</p>

## Description
*The Reporter* part of the Maxon bundle. Use this tool to send information about your machine's current status and/or operations.

## Requirements
- PHP 7.1+
- PHP `pcntl`, `json`, `posix`, `curl` extension enabled.

## Installation

***Convenient Oneliner™***:

```
curl -LO https://github.com/mergado/maxon-reporter/raw/master/build/reporter && chmod +x reporter && ./reporter
```

*Note: `reporter` executable will be downloaded into your current working directory.*

## Tips

- An `example` configuration is located at `./res/example.json`.
- Basic, example `gatherers` are located in `./res/gatherers` directory.
- Use the `./build/reporter` *(that's a compiled PHAR binary)* to manage the whole damn thing.

## Usage
Just run the reporter. It will keep running in the background.

```
./reporter
```

### Examples:
- `./reporter`
  - Run the reporter in standard mode. This means:
    1. Use the configuration file located at default path
      - Default config path is either `./config.json` or `./config/config.json`.
    2. Display the first gathering and sending data to target URL(s).
      - This is to see any potential problems with gatherers _(or their output)_ or with the resulting HTTP request to the target URL(s)
    3. **Daemonize**.
      - And keep running in background.
- `./reporter --try`
  - Reporter will execute gatherers once and print out:
    1. Gathered data.
    2. The final JSON payload which is going to be sent to target URL(s).
    3. HTTP code(s) returned from request(s) to target URL(s).
- `./reporter --pid`
  - Return the current running daemonized reporter's PID, if it exists.
- `./reporter --self-update`
  - Performs **self-update** from online repository.
- `./reporter --config some_dir/some_config.json`
  - Run the reporter as in standard mode _(daemonize)_ - but use the configuration specified in the `some_dir/some_config.json` file.
- `./reporter --interval 30`
  - Run the reporter as in standard mode - but gather data each 30 seconds instead of default 5 seconds.
  - The same as the one above, but daemonize the reporter and send it to background. Interval will be 5 seconds by default.
- `./reporter --help`
  - Display available options.

## Configuration

An `example.json` configuration file is provided. Following fields are mandatory, unless specified otherwise:
- `target` field: Either **a string or array of strings**. These strings are URLs where the reporter will send the result payload.
- `gatherers` field: An array of _gatherers_ to use. Use absolute paths or paths relative to your current working directory.
- `env` *(optional)* field: Environment variables which will then be available during a gatherer's execution.
- `payload` field: The template payload from which the resulting JSON will be constructed.

### Example minimal configuration
_See below._

## Gatherers

### What is a gatherer?
*A gatherer* is a script *(program)* that reports back some information about the current state of the machine *(or whatever is needed)*. The script's [shebang](https://en.wikipedia.org/wiki/Shebang_(Unix)) is honored, thus any language that supports it can be used to write *a gatherer*.

### How does a gatherer report information?
*A gatherer* is executed by *the reporter*. *The gatherer's* standard **output is then parsed** by *the reporter* as [string in `INI` format](https://en.wikipedia.org/wiki/INI_file) and values *(ie. variables)* defined this way are then available to use in the `payload` template *(which, again, is defined in the config file)*.

## Examples

### Example configuration

```json
{
	"target": [
        "http://localhost/maxon-display/report"
        "http://whatever.domain/i-dont-really-care/report"
    ],
	"gatherers": [
		"./gatherers/machine.sh",
		"./gatherers_py/some_script.py",
	],
	"env": {
	    "SOME_ENV_VAR_XYZ": "this env var is available in gatherers",
	},
	"payload": {
		"machine": {
			"name": "some_machine",
			"hostname": "${machine.hostname}",
			"title": "Some Machine"
		},
		"fields": {
			"machine.cpu.load_score": {
				"title": "Load Score",
				"type": "progress",
				"value": "${100 * machine.cpu.load_score}",
				"config": {
					"min": 0,
					"max": "300",
					"warning": "120",
					"alert": "150",
					"unit": "%"
				}
			},
			"machine.cpu.load_avg.1": {
				"title": "Load AVG 1",
				"type": "progress",
				"value": "${machine.cpu.load_avg.1}",
				"config": {
					"min": 0,
					"max": "${2 * machine.cpu.load_avg_max}",
					"warning": "${1.2 * machine.cpu.load_avg_max}",
					"alert": "${1.8 * machine.cpu.load_avg_max}"
				}
			}
        }
    }
}

```

### Example gatherer's output
If you implement your own gatherer _(which is expected)_, you need to format its output like [`INI`](https://en.wikipedia.org/wiki/INI_file).

```ini
storage.number_of_directories=456
storage.number_of_files=1534
books.last_word_in_the_newest_book=Serendipity
```

### Using variables in the payload template
```json
...
    "id.storage_dirs.or_whatever": { // This is an arbitrary ID and does not have to match the variable name.
        "title": "Directories",
        "type": "number",
        "value": "${storage.number_of_directories}", // The same ID (variable name) as reported by the gatherer.
        "config": {
            "unit": "dirs"
        }
    },
    "id.storage_files": {
        "title": "Files",
        "type": "number",
        "value": "${storage.number_of_files / 1000}", // Expressions can be used for advanced computations.
        "config": {
            "unit": "kilofiles"
        }
    },
    "id.word": {
        "title": "Last word",
        "type": "string",
        "value": "${books.last_word_in_the_newest_book}",
    },
...
```
